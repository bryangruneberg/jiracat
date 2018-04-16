<?php namespace App\Commands;

use stdClass;
use App\Jira;
use App\Issue;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class JiraDateIssueCommand extends JiraBaseCommand 
{
    protected $signature = 'di {issue : The issue to set a due date on} {--date=}';
    protected $description = 'Assign a due date to a JIRA issue';

    public function handle()
    {
        $issueKey = $this->argument('issue');
        $dueDateString = $this->option('date');
        $dueDate = "";
        if($dueDateString) {
            $dueDateCarbon = new Carbon($dueDateString);
            $dueDate = $dueDateCarbon->format('Y-m-d');
        }
        

        $issue = app(Jira::class)->getIssue($issueKey);

        if(!$issue)
        {
            $this->error("Requested issue cannot be found");
            return;
        }

        $currentDueDate = $issue->getDueDate();

        if(!$dueDate)
        {
            $dueDateString = $this->ask("Whats the due date?");
            $dueDateCarbon = new Carbon($dueDateString);
            $dueDate = $dueDateCarbon->format('Y-m-d');
        }

        $this->info("Changing due date" . (empty($currentDueDate) ? "" : " from " . $currentDueDate) . " to " . $dueDate);

        try 
        {
            app(Jira::class)->editIssue($issueKey, NULL, ['duedate' => $dueDate]);
        } 
        catch(\Exception $ex) 
        {
            $this->error($ex->getMessage());
            return;
        }

        $issue = app(Jira::class)->getIssue($issueKey);
        $currentDueDate = $issue->getDueDate();
        $this->info('Now due: ' . $currentDueDate);;
    }
}
