<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\MVC\View;

class PhpView implements ViewInterface
{
    /**
     * @var array The view data
     */
    protected array $data = [];

    /**
     * Create a new PHP view
     *
     * @param string $template
     * @param array $data
     */
    public function __construct(protected string $template, array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Set a view variable
     *
     * @param string $name
     * @param mixed $value
     * @return self
     */
    public function with(string $name, mixed $value): self
    {
        $this->data[$name] = $value;
        
        return $this;
    }

    /**
     * Set multiple view variables
     *
     * @param array $data
     * @return self
     */
    public function withData(array $data): self
    {
        $this->data = array_merge($this->data, $data);
        
        return $this;
    }

    /**
     * Render the view
     *
     * @return string
     * @throws \RuntimeException If the template file does not exist
     */
    public function render(): string
    {
        if (!file_exists($this->template)) {
            throw new \RuntimeException("View template {$this->template} does not exist");
        }
        
        // Extract data to make variables available in the template
        extract($this->data);
        
        // Start output buffering
        ob_start();
        
        // Include the template file
        include $this->template;
        
        // Get the content and clean the buffer
        $content = ob_get_clean();
        
        return $content;
    }
}
