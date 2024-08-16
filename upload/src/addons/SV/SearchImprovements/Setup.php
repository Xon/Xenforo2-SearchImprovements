<?php

namespace SV\SearchImprovements;

use SV\StandardLib\InstallerHelper;
use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Alter;
use XF\Entity\User;

/**
 * Class Setup
 *
 * @package SV\ReportImprovements
 */
class Setup extends AbstractSetup
{
    use InstallerHelper;
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    /**
     * Creates add-on tables.
     */
    public function installStep1(): void
    {
        $sm = $this->schemaManager();

        foreach ($this->getTables() as $tableName => $callback)
        {
            $sm->createTable($tableName, $callback);
            $sm->alterTable($tableName, $callback);
        }
    }

    /**
     * Alters core tables.
     */
    public function installStep2(): void
    {
        $sm = $this->schemaManager();

        foreach ($this->getAlterTables() as $tableName => $callback)
        {
            if ($sm->tableExists($tableName))
            {
                $sm->alterTable($tableName, $callback);
            }
        }
    }

    public function installStep3(): void
    {
        $this->applyDefaultPermissions();
    }

    public function upgrade2000001Step1(): void
    {
        $this->installStep1();
    }

    public function upgrade1682640718Step1(): void
    {
        $this->installStep2();
    }

    /**
     * Drops add-on tables.
     */
    public function uninstallStep1(): void
    {
        $sm = $this->schemaManager();

        foreach ($this->getTables() as $tableName => $callback)
        {
            $sm->dropTable($tableName);
        }
    }

    /**
     * Drops columns from core tables.
     */
    public function uninstallStep2(): void
    {
        $sm = $this->schemaManager();

        foreach ($this->getRemoveAlterTables() as $tableName => $callback)
        {
            if ($sm->tableExists($tableName))
            {
                $sm->alterTable($tableName, $callback);
            }
        }
    }

    protected function applyDefaultPermissions(int $previousVersion = 0): bool
    {
        $applied = false;

        if (!$previousVersion || $previousVersion < 1010300)
        {
            $applied = true;
            $this->applyGlobalPermissionByGroup('general', 'sv_searchOptions', [User::GROUP_REG]);
        }


        return $applied;
    }

    public function postInstall(array &$stateChanges): void
    {
        parent::postInstall($stateChanges);

        $this->checkElasticSearchOptimizableState();
    }

    public function postUpgrade($previousVersion, array &$stateChanges): void
    {
        $previousVersion = (int)$previousVersion;
        parent::postUpgrade($previousVersion, $stateChanges);

        $this->checkElasticSearchOptimizableState();
    }

    protected function getTables(): array
    {
        return [

        ];
    }

    protected function getAlterTables(): array
    {
        return [
            'xf_user_option' => function (Alter $table): void {
                $this->addOrChangeColumn($table, 'sv_default_search_order', 'varchar', 50)->setDefault('');
            },
            'xf_search' => function (Alter $table): void {
                // figure out how to use `json` type and determine if the mysql instance supports that type...
                $this->addOrChangeColumn($table, 'sv_debug_info', 'longtext')->nullable(true)->setDefault(null);
            },
        ];
    }

    protected function getRemoveAlterTables(): array
    {
        return [
            'xf_user_option' => function (Alter $table): void {
                $table->dropColumns(['sv_default_search_order']);
            },
            'xf_search' => function (Alter $table): void {
                $table->dropColumns(['sv_debug_info']);
            },
        ];
    }
}