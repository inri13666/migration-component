<?php

namespace Okvpn\Component\Migration\Tools;

use Doctrine\DBAL\Schema\SchemaDiff;

class SchemaDiffDumper
{
    const DEFAULT_SCHEMA_TEMPLATE = 'schema-diff-template.php.twig';
    const DEFAULT_NAMESPACE = 'Migrations\Schema';

    /** @var SchemaDiff */
    protected $schemaDiff;

    /** @var \Twig_Environment */
    protected $twig;

    /** @var string */
    protected $migrationPath;

    /** @var string */
    protected $template = self::DEFAULT_SCHEMA_TEMPLATE;

    /**
     * @param \Twig_Environment $twig
     */
    public function __construct(\Twig_Environment $twig)
    {
        $this->twig = $twig;
    }

    /**
     * @param string $template
     *
     * @return $this
     */
    public function setTemplate($template)
    {
        $this->template = $template;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function acceptSchemaDiff(SchemaDiff $schemaDiff)
    {
        $this->schemaDiff = $schemaDiff;
    }

    /**
     * @param string $version
     * @param array|null $allowedTables
     * @param array|null $extendedOptions
     * @param string|null $namespace
     * @param string $className
     *
     * @return string
     */
    public function dump(
        $version,
        array $allowedTables = null,
        array $extendedOptions = null,
        $className = null,
        $namespace = self::DEFAULT_NAMESPACE
    ) {
        $content = $this->twig->render(
            $this->template,
            [
                'schema' => $this->schemaDiff,
                'allowedTables' => $allowedTables,
                'namespace' => $namespace,
                'className' => $className ?: sprintf('Version%s', $version),
                'extendedOptions' => $extendedOptions,
            ]
        );

        return $content;
    }
}
