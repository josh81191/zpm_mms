<?php

declare(strict_types=1);

namespace Zpm;

use RuntimeException;

/**
 * Talks to an OpenAI-compatible chat endpoint (a local Ollama server or an approved provider).
 * Step 1 (plan) sends ONLY the question and a data-free schema description.
 * Step 2 (narrate) sends the question and aggregate counts (small groups already suppressed), never member records.
 */
final class AiPlanner
{
    public const UNAVAILABLE = 'The AI service is unavailable.';
    public const UNUSABLE = 'The AI service returned an unusable answer.';

    private array $config;
    private static string $lastError = '';

    public static function lastError(): string
    {
        return self::$lastError;
    }
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public static function enabled(array $config): bool
    {
        return ($config['enabled'] ?? false) === true && ($config['base_url'] ?? '') !== '' && ($config['model'] ?? '') !== '';
    }

    public function plan(string $question, string $guide): array
    {
        $system = 'You convert a question about a political party membership register into a JSON query plan. '
            . 'You never see data. Reply with ONE JSON object only, with keys: '
            . '"group_by" (array), "filters" (object), "measure" ("individuals","party_flow","party_moves" or "unit_attention"), "period" ({"from":"YYYY-MM-DD","to":"YYYY-MM-DD"} inclusive, on registration date (or on the date a change was recorded for party_flow/party_moves), or null), '
            . '"order" ("asc","desc" or null), "limit" (integer or null), '
            . '"show_table" (true only when the answer has many figures that a table presents much better than a sentence, otherwise false), '
            . '"refused" (string or null). '
            . 'Today is ' . date('Y-m-d') . '. Add a filter ONLY when the question explicitly names it (never add status, classification or party filters on your own); "all parties" or no party mentioned means no party filter. '
            . 'A party named in a question about where people moved FROM or TO is the filter, e.g. "where did MNF members go" gives filters.party ["mnf"]. '
            . 'If the question asks for personal details of individuals (names, EPCI numbers, contact details), '
            . "asks to predict or score individuals' political leanings or persuasion, or cannot be answered with counts, "
            . 'set "refused" to a short explanation and leave the rest empty. Use only the vocabulary below.' . "\n\n"
            . "Examples (question => plan):\n"
            . '"Which units need attention in the next campaign for ZPM?" => {"measure":"unit_attention","group_by":[],"filters":{"party":["zpm"]},"limit":5,"show_table":true}' . "\n"
            . '"Which 5 units in Aizawl need support for ZPM?" => {"measure":"unit_attention","group_by":[],"filters":{"party":["zpm"],"district":["Aizawl"]},"limit":5,"show_table":true}' . "\n"
            . '"How many members does each party have?" => {"measure":"individuals","group_by":["party"],"filters":{}}' . "\n"
            . '"Party migration in the last 12 months" => {"measure":"party_flow","group_by":[],"filters":{}}' . "\n"
            . '"Where did Congress members go?" => {"measure":"party_moves","group_by":[],"filters":{"party":["congress"]}}' . "\n\n" . $guide;

        $content = trim((string) preg_replace('/^```(?:json)?|```$/m', '', $this->chat($system, $question, true)));
        $plan = json_decode($content, true);
        if (!is_array($plan)) {
            throw new RuntimeException(self::UNUSABLE);
        }

        return $plan;
    }

    public function narrate(string $question, array $result): string
    {
        $facts = [
            'matching_total' => $result['total'],
            'scope' => $result['scope'],
            'interpretation' => $result['interpretation'],
            'figures' => array_map(static function (array $r) use ($result): array {
                if (isset($r['reasons'])) {
                    return ['unit' => $r['labels'][0], 'attention_score' => $r['cells'][0], 'reasons' => $r['reasons']];
                }
                if (isset($r['cells'])) {
                    return ['group' => implode(' / ', $r['labels']), 'values' => array_combine(array_slice($result['columns'], count($r['labels'])), $r['cells'])];
                }
                return ['group' => implode(' / ', $r['labels']), 'individuals' => $r['n'], 'percent_of_matching' => $r['pct']];
            }, $result['rows']),
            'notes' => $result['notes'],
        ];
        $system = "You write short answers for the office of a political party's general secretary. "
            . 'Answer the question in plain English using ONLY the figures provided: 2 to 5 sentences in one paragraph, no bullet points, no markdown, no tables, no invented numbers; copy numbers exactly as given. '
            . 'If the figures are units ranked by an attention score, name the top three units and state each one\'s "reasons" text as given, without adding other reasons or numbers, and mention that the score is an administrative indicator. '
            . 'State the key numbers, notable highest/lowest values or trends, and any caveat from the notes. '
            . 'Describe association only, never causation. Do not speculate about individuals. '
            . 'If there are no figures, say no matching records were found.';

        $text = trim($this->chat($system, "Question: {$question}\nFigures: " . json_encode($facts, JSON_UNESCAPED_UNICODE), false));
        if ($text === '') {
            throw new RuntimeException(self::UNUSABLE);
        }

        return mb_substr($text, 0, 1500);
    }

    private function chat(string $system, string $user, bool $json): string
    {
        $body = [
            'model' => $this->config['model'],
            'temperature' => 0,
            'max_tokens' => $json ? 600 : 500,
            'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $user]],
        ];
        if ($json) {
            $body['response_format'] = ['type' => 'json_object'];
        }

        $headers = ['Content-Type: application/json'];
        if (($this->config['api_key'] ?? '') !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->config['api_key'];
        }
        if (!function_exists('curl_init')) {
            self::$lastError = 'The PHP curl extension is not enabled.';
            error_log('AI request failed: ' . self::$lastError);
            throw new RuntimeException(self::UNAVAILABLE);
        }
        $handle = curl_init(rtrim($this->config['base_url'], '/') . '/chat/completions');
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => (int) ($this->config['timeout'] ?? 60),
        ]);
        $response = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($handle);
        curl_close($handle);

        if (!is_string($response) || $status < 200 || $status >= 300) {
            self::$lastError = 'HTTP status ' . $status . ($curlError !== '' ? '; curl: ' . $curlError : '') . (is_string($response) ? '; response: ' . mb_substr($response, 0, 300) : '');
            error_log('AI request failed with ' . self::$lastError);
            throw new RuntimeException(self::UNAVAILABLE);
        }
        $content = json_decode($response, true)['choices'][0]['message']['content'] ?? null;
        if (!is_string($content)) {
            throw new RuntimeException(self::UNUSABLE);
        }

        return $content;
    }
}
