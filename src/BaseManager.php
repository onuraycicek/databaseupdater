<?php

namespace Onuraycicek\DatabaseUpdater;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log as LogFacade;

trait processes
{
    private $processes = [];

    public function addProcess($process)
    {
        $this->processes[] = $process;
    }

    public function getProcesses()
    {
        return $this->processes;
    }
}

trait log
{
    private $log = [];

    public function addProcess($log)
    {
        $this->log[] = $log;
        // if (isset($log['error'])) {
        //     LogFacade::error($log);
        // } else {
        //     LogFacade::info($log);
        // }
    }

    public function getProcesses()
    {
        return $this->log;
    }
}

class BaseManager
{
    use processes;

    public function callArtisan($command)
    {
        $options = [];
        if (strpos($command, 'migrate') !== false) {
            $options = ['--force'];
        }
        $command .= ' ' . implode(' ', $options);
        
        Artisan::call($command);
        $artisanOutput = Artisan::output();
        if (in_array('Error', str_split($artisanOutput, 5))) {
            $this->addProcess([
                'status' => 'error',
                'reason' => 'Error while '.$command,
                'error' => $artisanOutput,
            ]);
            throw ([
                'error' => $artisanOutput,
                'processes' => $this->getProcesses(),
                'command' => $command,
            ]);
        } else {
            $this->addProcess([
                'status' => 'success',
                'reason' => $command,
                'output' => $artisanOutput,
            ]);
        }
    }
}
