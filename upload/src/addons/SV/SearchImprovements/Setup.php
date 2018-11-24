<?php

namespace SV\ReportImprovements;

use SV\Utils\InstallerHelper;
use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;
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
    public function installStep1()
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
    public function installStep2()
    {
        $sm = $this->schemaManager();

        foreach ($this->getAlterTables() as $tableName => $callback)
        {
            $sm->alterTable($tableName, $callback);
        }
    }

    public function installStep3()
    {
        $this->applyDefaultPermissions();
    }

    public function upgrade2000001Step1()
    {
        $this->installStep1();
    }

    public function upgrade2000001Step2()
    {
        $this->installStep2();
    }

    /**
     * Drops add-on tables.
     */
    public function uninstallStep1()
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
    public function uninstallStep2()
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


    /**
     * @param int|null $previousVersion
     * @return bool True if permissions were applied.
     */
    protected function applyDefaultPermissions($previousVersion = null)
    {
        $applied = false;
        $previousVersion = (int)$previousVersion;

        if (!$previousVersion || $previousVersion < 1010300)
        {
            $applied = true;
            $this->applyGlobalPermissionByGroup('general', 'sv_searchOptions', [User::GROUP_REG]);
        }


        return $applied;
    }
    /**
     * @return array
     */
    protected function getTables()
    {
        $tables = [];

        return $tables;
    }

    /**
     * @return array
     */
    protected function getAlterTables()
    {
        $tables = [];

        $tables['xf_user_option'] = function (Alter $table) {
            $this->addOrChangeColumn($table, 'sv_default_search_order', 'varchar', 50)->setDefault('');
        };

        return $tables;
    }

    /**
     * @return array
     */
    protected function getRemoveAlterTables()
    {
        $tables = [];

        $tables['xf_user_option'] = function (Alter $table) {
            $table->dropColumns(['sv_default_search_order']);
        };

        return $tables;
    }
}