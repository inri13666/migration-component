<?php

namespace Okvpn\Component\Migration\Twig;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;

class SchemaDumperExtension extends \Twig_Extension
{
    /** @var EntityManager */
    protected $doctrine;

    /** @var AbstractPlatform */
    protected $platform;

    /** @var Column */
    protected $defaultColumn;

    /** @var array */
    protected $defaultColumnOptions = [];

    /** @var array */
    protected $optionNames = [
        'default',
        'notnull',
        'length',
        'precision',
        'scale',
        'fixed',
        'unsigned',
        'autoincrement'
    ];

    /**
     * @param EntityManager $doctrine
     */
    public function __construct(EntityManager $doctrine)
    {
        $this->doctrine = $doctrine;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'schema_dumper_extension';
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('okvpn_migration_get_schema_column_options', [$this, 'getColumnOptions']),
        ];
    }

    /**
     * @param Column $column
     * @return array
     */
    public function getColumnOptions(Column $column)
    {
        $defaultOptions = $this->getDefaultOptions();
        $platform = $this->getPlatform();
        $options = [];

        foreach ($this->optionNames as $optionName) {
            $value = $this->getColumnOption($column, $optionName);
            if ($value !== $defaultOptions[$optionName]) {
                $options[$optionName] = $value;
            }
        }

        $comment = $column->getComment();
        if ($platform && $platform->isCommentedDoctrineType($column->getType())) {
            file_put_contents('c:/uuu/yahoo.txt', var_export([$comment, $column->getType(), $platform->getDoctrineTypeComment($column->getType())], true), FILE_APPEND);
            $comment .= $platform->getDoctrineTypeComment($column->getType());
        }
        if (!empty($comment)) {
            $options['comment'] = $comment;
        }

        return $options;
    }

    /**
     * @param Column $column
     * @param string $optionName
     * @return mixed
     */
    protected function getColumnOption(Column $column, $optionName)
    {
        $method = "get" . $optionName;

        return $column->$method();
    }

    /**
     * @return AbstractPlatform
     */
    protected function getPlatform()
    {
        if (!$this->platform) {
            $this->platform = $this->doctrine->getConnection()->getDatabasePlatform();
        }

        return $this->platform;
    }

    /**
     * @return array
     */
    protected function getDefaultOptions()
    {
        if (!$this->defaultColumn) {
            $this->defaultColumn = new Column('_template_', Type::getType(Type::STRING));
        }
        if (!$this->defaultColumnOptions) {
            foreach ($this->optionNames as $optionName) {
                $this->defaultColumnOptions[$optionName] = $this->getColumnOption($this->defaultColumn, $optionName);
            }
        }

        return $this->defaultColumnOptions;
    }
}
