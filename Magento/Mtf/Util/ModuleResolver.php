<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\Mtf\Util;

/**
 * Class ModuleResolver, resolve module path based on enabled modules of target Magento instance
 *
 * @api
 */
class ModuleResolver
{
    /**
     * Environment field name for module whitelist
     */
    const MODULE_WHITELIST = 'module_whitelist';

    /**
     * @var array|null
     */
    protected $enabledModules = null;

    /**
     * @var array|null
     */
    protected $enabledModulePaths = null;

    /**
     * @var \Magento\Mtf\Config\DataInterface
     */
    protected $configuration;

    /**
     * @var string
     */
    protected $adminTokenUrl = "rest/V1/integration/admin/token";

    /**
     * @var string
     */
    protected $moduleUrl = "rest/V1/modules";

    /**
     * List of known directory that does not map to a Magento module
     *
     * @var array
     */
    protected $knownDirectories = ['SampleData' => 1];

    /**
     * @var ModuleResolver
     */
    private static $instance = null;

    /**
     * @return ModuleResolver
     */
    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new ModuleResolver();
        }
        return self::$instance;
    }

    /**
     * Constructor
     *
     * @param \Magento\Mtf\Config\DataInterface $configuration
     */
    private function __construct(\Magento\Mtf\Config\DataInterface $configuration = null)
    {
        if ($configuration) {
            $this->configuration = $configuration;
        } else {
            $this->configuration = \Magento\Mtf\ObjectManagerFactory::getObjectManager()
                ->get('Magento\Mtf\Config\GlobalConfig');
        }
    }

    /**
     * Return an array of enabled modules of target Magento instance
     *
     * @return array
     */
    public function getEnabledModules()
    {
        if (isset($this->enabledModules)) {
            return $this->enabledModules;
        }

        $token = $this->getAdminToken();
        if (!$token || !is_string($token)) {
            $this->enabledModules = [];
            return $this->enabledModules;
        }

        $url = $_ENV['app_frontend_url'] . $this->moduleUrl;

        $headers = [
            'Authorization: Bearer ' . $token,
        ];
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);

        if (!$response) {
            $this->enabledModules = [];
        } else {
            $this->enabledModules = json_decode($response);
        }
        return $this->enabledModules;
    }

    /**
     * Return an array of module whitelist that not exist in target Magento instance
     *
     * @return array
     */
    protected function getModuleWhitelist()
    {
        $moduleWhitelist = getenv(self::MODULE_WHITELIST);

        if (empty($moduleWhitelist)) {
            return [];
        }
        return array_map('trim', explode(',', $moduleWhitelist));
    }

    /**
     * Return the modules path based on which modules are enabled in the target Magento instance
     *
     * @return array
     */
    public function getModulesPath()
    {
        if (isset($this->enabledModulePaths)) {
            return $this->enabledModulePaths;
        }

        $enabledModules = $this->getEnabledModules();
        $modulePath = defined('MTF_TESTS_MODULE_PATH') ? MTF_TESTS_MODULE_PATH : MTF_TESTS_PATH;
        $allModulePaths = glob($modulePath . '*/*');
        if (empty($enabledModules)) {
            $this->enabledModulePaths = $allModulePaths;
            return $this->enabledModulePaths;
        }

        $enabledModules = array_merge($enabledModules, $this->getModuleWhitelist());
        $enabledDirectories = [];
        foreach ($enabledModules as $module) {
            $directoryName = explode('_', $module)[1];
            $enabledDirectories[$directoryName] = $directoryName;
        }

        foreach ($allModulePaths as $index => $modulePath) {
            $moduleShortName = basename($modulePath);
            if (!isset($enabledDirectories[$moduleShortName]) && !isset($this->knownDirectories[$moduleShortName])) {
                unset($allModulePaths[$index]);
            }
        }

        $this->enabledModulePaths = $allModulePaths;
        return $this->enabledModulePaths;
    }

    /**
     * Get the API token for admin
     *
     * @return string|bool
     */
    protected function getAdminToken()
    {
        $login = $this->configuration->get('application/0/backendLogin/0/value');
        $password = $this->configuration->get('application/0/backendPassword/0/value');
        if (!$login || !$password || !isset($_ENV['app_frontend_url'])) {
            return false;
        }

        $url = $_ENV['app_frontend_url'] . $this->adminTokenUrl;
        $data = [
            'username' => $login,
            'password' => $password
        ];
        $headers = [
            'Content-Type: application/json',
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        if (!$response) {
            return $response;
        }
        return json_decode($response);
    }
}
