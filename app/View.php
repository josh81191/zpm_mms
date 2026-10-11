<?php

declare(strict_types=1);

namespace Zpm;

final class View
{
    private string $templatesPath;

    public function __construct(string $templatesPath)
    {
        $this->templatesPath = $templatesPath;
    }

    public function render(string $template, array $data = []): void
    {
        $templateFile = $this->templatesPath . DIRECTORY_SEPARATOR . $template . '.php';
        extract($data, EXTR_SKIP);
        require $templateFile;
    }
}

