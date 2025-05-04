<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\MVC\View;

interface ViewInterface
{
    /**
     * Set a view variable
     *
     * @param string $name
     * @param mixed $value
     * @return self
     */
    public function with(string $name, mixed $value): self;

    /**
     * Set multiple view variables
     *
     * @param array $data
     * @return self
     */
    public function withData(array $data): self;

    /**
     * Render the view
     *
     * @return string
     */
    public function render(): string;
}
