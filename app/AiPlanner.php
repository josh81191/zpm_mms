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

    public function __construct(private array $config)
    {
    }

    public static function enabled(array $config): bool
    {
        return ($config['enabled'] ?? false) === true && ($config['base_url'] ?? '') !== '' && ($config['model'] ?? '') !== '';
    }

    public function plan(string $question, string $guide): array
    {
        $system = 'You convert a question about a political party membership register into a JSON query plan. '
            . 'You never see data. Reply with ONE JSON object only, with keys: '
            . '"group_by" (array), "filters" (object), "period" ({"from":"YYYY-MM-DD","to":"YYYY-MM-DD"} inclusive, on registration date, or null), '
            . '"order" ("asc","desc" or null), "limit" (integer or null), '
            . '"show_table" (true only when the answer has many figures that a table presents much better than a sentence, otherwise false), '
            . '"refused" (string or null). '
            . 'Today is ' . date('Y-m-d') . '. If the question asks for personal details of individuals (names, EPCI numbers, contact details), '
            . "asks to predict or score individuals' political leanings or persuasion, or cannot be answered with counts, "
            . 'set "refused" to a short explanation and leave the rest empty. Use only the vocabulary below.' . "\n\n" . $guide;

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
            'figures' => array_map(static fn (array $r): array => ['group' => implode(' / ', $r['labels']), 'individuals' => $r['n'], 'percent_of_matching' => $r['pct']], $result['rows']),
            'notes' => $result['notes'],
        ];
        $system = "You write short answers for the office of a political party's general secretary. "
            . 'Answer the question in plain English using ONLY the figures provided: 2 to 5 sentences, no markdown, no tables, no invented numbers. '
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
        curl_close($handle);

        if (!is_string($response) || $status < 200 || $status >= 300) {
            error_log('AI request failed with HTTP status ' . $status);
            throw new RuntimeException(self::UNAVAILABLE);
        }
        $content = json_decode($response, true)['choices'][0]['message']['content'] ?? null;
        if (!is_string($content)) {
            throw new RuntimeException(self::UNUSABLE);
        }

        return $content;
    }
}
