<?php

namespace MLRedirect;

use Concrete\Core\Application\Application;
use Concrete\Core\Http\Request;
use Concrete\Core\Package\PackageService;
use DeviceDetector\DeviceDetector;
use DeviceDetector\Parser\OperatingSystem;

class OSDetector
{
    /**
     * @var \Concrete\Core\Application\Application
     */
    protected $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->registerAutoload();
    }

    /**
     * @param Request $request
     *
     * @return array|string
     */
    public function detectOS(Request $request)
    {
        $dd = new DeviceDetector($request->headers->get('User-Agent'));
        $dd->setCache($this->app->make(OSDetector\Cache::class));
        $dd->parse();
        $shortName = (string) $dd->getOs('short_name');
        $family = (string) OperatingSystem::getOsFamily($shortName);

        return $family === 'Unknown' ? '' : $family;
    }

    /**
     * @return string[]
     */
    public function getOperatingSystemsList()
    {
        return array_keys(OperatingSystem::getAvailableOperatingSystemFamilies());
    }

    private function registerAutoload()
    {
        if (!class_exists(DeviceDetector::class)) {
            $packageService = $this->app->make(PackageService::class);
            $package = $packageService->getByHandle('redirect');
            $packageController = $package->getController();
            require_once $packageController->getPackagePath() . '/vendor/autoload.php';
        }
    }
}
