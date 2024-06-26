<?php

namespace Valet\Contracts;

interface ServiceManager
{
    /**
     * Start the given services.
     *
     * @param
     *
     * @return void
     */
    public function start($services);

    /**
     * Stop the given services.
     *
     * @param
     *
     * @return void
     */
    public function stop($services);

    /**
     * Restart the given services.
     *
     * @param
     *
     * @return void
     */
    public function restart($services);

    /**
     * Enable the given services.
     *
     * @param
     *
     * @return bool
     */
    public function enable($services);

    /**
     * Disable the given services.
     *
     * @param
     *
     * @return bool
     */
    public function disable($services);

    /**
     * Check if service is disabled.
     *
     * @param mixed $service Service name
     *
     * @return bool
     */
    public function disabled($service);

    /**
     * Status the given services.
     *
     * @param
     *
     * @return string
     */
    public function status($services);

    /**
     * Determine if service manager is available on the system.
     *
     * @return bool
     */
    public function isAvailable();

    /**
     * Status of the given services.
     *
     * @param mixed $services Service name
     *
     * @return void
     */
    public function printStatus($services);
}
