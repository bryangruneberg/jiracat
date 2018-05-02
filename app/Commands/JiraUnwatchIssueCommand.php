<?php namespace App\Commands;

use stdClass;
use App\Jira;
use App\Issue;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class JiraUnwatchIssueCommand extends JiraBaseCommand 
{
    protected $signature = 'uwi {issue : The issue to set a due date on} {--user= : The user to unwatch as}';
    protected $description = 'Unwatch an issue';

    public function handle()
    {
        $issueKey = $this->argument('issue');
        $issue = app(Jira::class)->getIssue($issueKey);

        if(!$issue)
        {
            $this->error("Requested issue cannot be found");
            return;
        }

        $user = $this->getUsername($this->option('user'));
        if(!$user)
        {
            $currentUser = app(Jira::class)->getCurrentUserData();
            if(isset($currentUser['name']))
            {
                $user = $currentUser['name'];
            }
        }

        $watchers = app(Jira::class)->getIssueWatchers($issueKey);
        $currentWatchers = [];
        if(isset($watchers['watchers'])) 
        {
            foreach($watchers['watchers'] as $watcher) {
                $currentWatchers[] = $watcher['name'];
            }
        } 

        if(in_array($user, $currentWatchers)) 
        {
            $this->line('Current watchers: ' . implode(', ' , $currentWatchers));
            $this->line("Removing " . $user . " from watchers");
            try 
            {
                app(Jira::class)->unsetIssueWatchers($issueKey, [$user]);
            } 
            catch(\Exception $ex) 
            {
                $this->error($ex->getMessage());
                return;
            }

            $watchers = app(Jira::class)->getIssueWatchers($issueKey);
            $nowWatchers = [];
            if(isset($watchers['watchers'])) 
            {
                foreach($watchers['watchers'] as $watcher) {
                    $nowWatchers[] = $watcher['name'];
                }
            } 
            $this->line('Now watchers: ' . implode(', ' , $nowWatchers));
        }
        else 
        {
            $this->error($user . " is already not watching " . $issueKey);
        }
    }
}
