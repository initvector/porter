<?php
namespace Garden\Porter\Package;

use \Garden\Porter\ExportModel;

/**
 * @copyright Vanilla Forums Inc. 2010-2015
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

/**
 * Generic controller implemented by forum-specific ones.
 */
abstract class Base {

    /** @var array Database connection info */
    protected $DbInfo = array();

    /** @var array Required tables, columns set per exporter */
    protected $SourceTables = array();

    /** @var ExportModel */
    protected $Ex = null;

    protected $frontend;

    protected $name;

    protected $prefix;

    /** Forum-specific export routine */
    abstract protected function ForumExport($Ex);

    /**
     * Construct and set the controller's properties from the posted form.
     */
    public function __construct($frontend, $ex) {
        $ex->Controller = $this;
        $ex->Prefix = $frontend->getOpt('prefix');

        /**
         * Selective exports
         * 1. Get the comma-separated list of tables and turn it into an array
         * 2. Trim off the whitespace
         * 3. Normalize case to lower
         * 4. Save to the ExportModel instance
         */
        $RestrictedTables = $frontend->getOpt('tables', false);
        if (!empty($RestrictedTables)) {
            $RestrictedTables = explode(',', $RestrictedTables);

            if (is_array($RestrictedTables) && !empty($RestrictedTables)) {
                $RestrictedTables = array_map('trim', $RestrictedTables);
                $RestrictedTables = array_map('strtolower', $RestrictedTables);

                $ex->RestrictedTables = $RestrictedTables;
            }
        }

        $this->Ex = $ex;
    }

    /**
     * Set CDN file prefix if one is given.
     *
     * @return string
     */
    public function CdnPrefix() {
        $Cdn = rtrim($this->Param('cdn', ''), '/');
        if ($Cdn) {
            $Cdn .= '/';
        }

        return $Cdn;
    }

    /**
     * Logic for export process.
     */
    public function DoExport() {
        // Test connection
        $Msg = $this->Ex->TestDatabase();
        if ($Msg === true) {

            // Test src tables' existence structure
            $Msg = $this->Ex->VerifySource($this->SourceTables);
            if ($Msg === true) {
                // Good src tables - Start dump
                $this->Ex->UseCompression(true);
                set_time_limit(60 * 60);

                $this->ForumExport($this->Ex);

                // Write the results.  Send no path if we don't know where it went.
                if ($this->Param('destpath', false)) {
                    $destination = false;
                } else {
                    $destination = $this->Ex->Path;
                }
                return array(
                    'comments' => $this->Ex->Comments,
                    'file' => $destination
                );
            }
        }

        return false;
    }

    public function getName() {
        return $this->name;
    }

    public function getPrefix() {
        return $this->prefix;
    }

    /**
     * User submitted db connection info.
     */
    public function HandleInfoForm() {
        $this->DbInfo = array(
            'dbhost' => $_POST['dbhost'],
            'dbuser' => $_POST['dbuser'],
            'dbpass' => $_POST['dbpass'],
            'dbname' => $_POST['dbname'],
            'type' => $_POST['type'],
            'prefix' => preg_replace('/[^A-Za-z0-9_-]/', '', $_POST['prefix'])
        );
    }

    /**
     * Retrieve a parameter passed to the export process.
     *
     * @param string $Name
     * @param mixed $Default Fallback value.
     * @return mixed Value of the parameter.
     */
    public function Param($Name, $Default = false) {
        if (isset($_POST[$Name])) {
            return $_POST[$Name];
        } elseif (isset($_GET[$Name])) {
            return $_GET[$Name];
        } else {
            return $Default;
        }
    }
}
