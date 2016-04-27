<?php
use Composer\Script\CommandEvent;

/**
 * Class ScriptHandler
 */
class ComposerBootstrap
{
    /**
     * Builds the bootstrap file.
     *
     * The bootstrap file contains PHP file that are always needed by the application.
     * It speeds up the application bootstrapping.
     *
     * @param $event CommandEvent A instance
     */
    public static function checkConfiguration(CommandEvent $event)
    {
        $rootPath              = self::getSymfonyRootPath();
        $configPath            = $rootPath . "/app/config/";
        $configurationBaseFile = $configPath . "parameters.yml.dist";
        $configurationFile     = $configPath . "parameters.yml";
        $isNewInstall          = !file_exists($configurationFile);
        //$configuration         = file_get_contents($configurationBaseFile);

        if ($isNewInstall) {
            copy($configurationBaseFile, $configurationFile);

            self::createDatabase();
            self::resetRootLogin();
            self::importExampleApplications();
            self::updateEpsgCodes();
        }

        self::clearCache();
    }

    /**
     * Is windows
     *
     * @return bool
     */
    protected static function isWindows()
    {
        static $isWindows;
        if ($isWindows === null) {
            $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        }
        return $isWindows;
    }

    /**
     * Clear cache but let sessions alive
     */
    protected static function clearCache()
    {
        $isWindows = self::isWindows();
        $cachePath = "var/cache";
        if (!$isWindows) {
            self::printStatus("Clear cache");
            foreach (glob($cachePath . "/*/*") as $filePath) {
                $fileInfo = explode("/", $filePath);
                $fileName = end($fileInfo);
                if ($fileName == "sessions") {
                    continue;
                }
                //$filePath = escapeshellarg($filePath);
                echo `rm -rf $filePath`;
            }
        }
    }

    /**
     * Enable write cache, logs and upload folder by user and group
     */
    protected static function allowWriteLogs()
    {
        if (!self::isWindows()) {
            self::printStatus("Enable write cache, logs and upload for user and user group");

            echo `chmod -R ug+wX app/cache`;
            echo `chmod -R ug+wX app/logs`;
            echo `chmod -R ug+wX web/uploads`;
        }
    }

    /**
     * Crate database, schema and force update
     */
    protected static function createDatabase()
    {
        self::printStatus("Create and prepare database");

        echo `php app/console doctrine:database:create`;
        echo `php app/console doctrine:schema:create`;
        //echo `php app/console doctrine:schema:update --force`;
    }

    /**
     * Reset root user login
     *
     * @param string $userName
     * @param string $password
     * @param string $userEmail
     */
    protected static function resetRootLogin($userName = "root", $password = "root", $userEmail = "root@localhost")
    {
        $userName  = escapeshellarg($userName);
        $userEmail = escapeshellarg($userEmail);
        $password  = escapeshellarg($password);

        self::printStatus("Reset user password");

        `php app/console fom:user:resetroot --username $userName --password $password --email $userEmail --silent`;

        self::printStatus("ATTENTION");
        echo "User $userName account password is: $password. Don't forget to change it!\n";
    }

    /**
     * Get symfony root path
     *
     * @return string
     */
    protected static function getSymfonyRootPath()
    {
        static $path;
        if (!$path) {
            $path = realpath(__DIR__ . "/..");
        }
        return $path;
    }

    /**
     * Import or update EPSG codes
     */
    protected static function updateEpsgCodes()
    {
        self::printStatus("Update EPSG codes");
        echo `php app/console doctrine:fixtures:load --fixtures=mapbender/src/Mapbender/CoreBundle/DataFixtures/ORM/Epsg/ --append`;
    }

    /**
     * Import example applications from "app/config/mapbender.yml" file
     */
    protected static function importExampleApplications()
    {
        self::printStatus("Import example mapbender applications");
        echo `php app/console doctrine:fixtures:load --fixtures=mapbender/src/Mapbender/CoreBundle/DataFixtures/ORM/Application/ --append`;
    }

    /**
     * @return string
     */
    protected static function printStatus($title)
    {
        echo "\n[$title]\n";
    }
}
