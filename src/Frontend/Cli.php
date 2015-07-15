<?php
namespace Garden\Porter\Frontend;

class Cli extends Base {
    protected $args;

    protected $exportModel;

    public function argsSet() {
        return $this->args instanceof \Garden\Cli\Args;
    }

    public function doOutput($output, $newLine = true) {
        if ($newLine) {
            $output .= PHP_EOL;
        }

        fwrite(STDOUT, $output);
    }

    public function doPort($packageName) {
        $packageClass = "\\Garden\\Porter\\Package\\{$packageName}";

        if (class_exists($packageClass)) {
            $package = new $packageClass($this, $this->exportModel);
            return $package->DoExport();
        }

        return false;
    }

    public function exec() {
        $exportModel = $this->getExportModel();
        $command = $this->getCommand();

        switch ($command) {
            case 'port':
                $this->doPort($this->getOpt('type'));
                break;
            default:
        }
    }

    public function getCommand() {
        if ($this->argsSet()) {
            return $this->args->getCommand();
        } else {
            return false;
        }
    }

    protected function getExportModel() {
        if ($this->exportModel instanceof \Garden\Porter\ExportModel) {
            return $this->exportModel;
        }

        $this->exportModel = new \Garden\Porter\ExportModel($this);
        $this->exportModel->setConnection(
            $this->getOpt('host', '127.0.0.1'),
            $this->getOpt('user'),
            $this->getOpt('pass', null),
            $this->getOpt('dbname')
        );
        $this->exportModel->FilenamePrefix = $this->getOpt('dbname');

        return $this->exportModel;
    }

    public function getOpt($arg, $default = false) {
        if ($this->argsSet()) {
            return $this->args->getOpt($arg, $default);
        } else {
            return $default;
        }
    }

    protected function setOpts() {
        if ($this->argsSet()) {
            return;
        }

        global $argv;

        $cli = new \Garden\Cli\Cli;

        $cli->description('Dump some information from your database.');

        $cli->command('port')
            ->opt('avatars', 'Enables exporting avatars from the database if supported.')
            ->opt('cdn', 'Prefix to be applied to file paths.')
            ->opt('destpath', 'Define destination path for the export file.')
            ->opt('dbname', 'Database name.', true)
            ->opt('files', 'Enables exporting attachments from database if supported.')
            ->opt('host', 'IP address or hostname to connect to. Default is 127.0.0.1.')
            ->opt('password', 'Database connection password.')
            ->opt('prefix', 'The table prefix in the database.')
            ->opt('tables', 'Selective export, limited to specified tables, if provided.')
            ->opt('type', 'Type of forum we\'re freeing you from.', true)
            ->opt('user', 'Database connection username.', true);

        $this->args = $cli->parse($argv);
    }
}
