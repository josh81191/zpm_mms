<?php

declare(strict_types=1);

namespace Zpm;

final class View
{
    public function __construct(private string $templatesPath)
    {
    }

    public function render(string $template, array $data = []): void
    {
        $templateFile = $this->templatesPath . DIRECTORY_SEPARATOR . $template . '.php';
        extract($data, EXTR_SKIP);
        require $templateFile;
    }
}

