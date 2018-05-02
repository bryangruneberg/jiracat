<?php namespace App\Commands;

use App\Jira;
use App\Issue;
use Illuminate\Console\Command;

class JiraDumpConfigCommand extends JiraBaseCommand 
{
    protected $signature = 'dc';
    protected $description = 'Dump JIRA config';

    public function handle()
    {
        $config = app(Jira::class)->resolveConfigArray();

        $this->info('Environment: ' . app()->environment());

        $this->info('YML: ' );
        foreach($config['ymlfiles'] as $yml) {
          $this->line(' - ' . $yml);
        }
    }
}
