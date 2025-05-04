<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\MVC\View;

use EaseAppPHP\HighPer\Framework\Config\ConfigProvider;

class ViewFactory
{
    /**
     * @var string The base path for views
     */
    protected string $viewPath;
    
    /**
     * @var string The file extension for view templates
     */
    protected string $extension;

    /**
     * Create a new view factory
     *
     * @param ConfigProvider $config
     */
    public function __construct(ConfigProvider $config)
    {
        $this->viewPath = $config->get('view.path', 'resources/views');
        $this->extension = $config->get('view.extension', 'php');
    }

    /**
     * Create a new view instance
     *
     * @param string $template
     * @param array $data
     * @return ViewInterface
     */
    public function make(string $template, array $data = []): ViewInterface
    {
        $templatePath = $this->resolveTemplatePath($template);
        
        return new PhpView($templatePath, $data);
    }

    /**
     * Resolve the template path
     *
     * @param string $template
     * @return string
     */
    protected function resolveTemplatePath(string $template): string
    {
        // Remove file extension if it's already there
        $template = preg_replace('/\.' . preg_quote($this->extension, '/') . '$/', '', $template);
        
        // Replace dots with directory separators
        $template = str_replace('.', DIRECTORY_SEPARATOR, $template);
        
        // Build the full path
        return $this->viewPath . DIRECTORY_SEPARATOR . $template . '.' . $this->extension;
    }
}
