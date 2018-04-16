<?php namespace App\Commands;

use stdClass;
use App\Jira;
use App\Issue;
use Illuminate\Console\Command;

class JiraPrioritizeIssueCommand extends JiraBaseCommand 
{
    protected $signature = 'pi {issue : The issue to prioritize} {--priority=}';
    protected $description = 'Assign a priority to a JIRA issue';

    public function handle()
    {
        $issueKey = $this->argument('issue');
        $priority = $this->option('priority');

        $issue = app(Jira::class)->getIssue($issueKey);

        if(!$issue)
        {
            $this->error("Requested issue cannot be found");
            return;
        }

        $currentPriority = $issue->getPriorityName();

        $possiblePriorities = app(Jira::class)->getPrioritiesList();
        $possiblePriorities[0] = 'Exit';

        while(!in_array($priority, $possiblePriorities)) 
        {
            if($priority) {
                $this->error("$priority is not an acceptable priority: " . implode(', ', $possiblePriorities));
            }
            $choice = $this->choice("What priority would you like to use?", $possiblePriorities);
            $priority = $choice;
        }

        if($priority == "Exit") 
        {
            return;
        }

        $this->info("Changing priority from " . $currentPriority . " to " . $priority);

        try 
        {
            app(Jira::class)->editIssue($issueKey, NULL, ['priority' => ['name' => $priority]]);
        } 
        catch(\Exception $ex) 
        {
            $this->error($ex->getMessage());
            return;
        }

        $issue = app(Jira::class)->getIssue($issueKey);
        $currentPriority = $issue->getPriorityName();
        $this->info('Now priority: ' . $currentPriority);;
    }
}
