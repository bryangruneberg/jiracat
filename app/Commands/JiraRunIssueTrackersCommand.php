<?php namespace App\Commands;

use App\Jira;
use App\Issue;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Carbon\DateTimeZone;

class JiraRunIssueTrackersCommand extends JiraBaseCommand 
{
    protected $signature = 'issue-tracker';
    protected $description = 'Run the issue tracker (long running command)';

    public function handle()
    {
       $serverTime = app(Jira::class)->getServerTime();
       $serverNow = new Carbon($serverTime);
       $localNow = Carbon::now(new \DateTimeZone(env('JIRA_TZ')));
       if($this->output->isVerbose()) 
       {
         $this->warn("Server Time: " . $serverTime);
         $this->warn("Server Now Time: " . $serverNow->toIso8601String());
         $this->warn("Local Now Time: " . $localNow->toIso8601String());
       }

       app(Jira::class)->prepareStorageDirectory();
       $storageDir = app(Jira::class)->getStorageDirectory();

       $config = app(Jira::class)->resolveConfigArray();
       $trackers = $config['trackers'] ?? [];
       foreach($trackers as $trackerId => $tracker) 
       {
         $jqlBase = $tracker['jql'] ?? '';
         if(!$jqlBase) 
         {
           $this->error("No base JQL for tracker " . $trackerId);
           continue;
         }

         $trackerFile = $storageDir . DIRECTORY_SEPARATOR . $trackerId . "_tracker.json";
         if($this->output->isVerbose()) 
         {
           $this->info("Using tracker file " . $trackerFile);
         }

         $trackerData = [];
         if(file_exists($trackerFile)) 
         {
           if($this->output->isVerbose()) 
           {
             $this->info("Found a tracker file, loading it up");
           }

           $trackerData = json_decode(file_get_contents($trackerFile), TRUE);
         }

         $jql = $jqlBase;
         if(isset($trackerData['lastrun']))
         {
           $lastRun = new Carbon($trackerData['lastrun']);
           $jql = '(' . $jqlBase . ') AND ( UPDATED >= "'. $lastRun->format('Y-m-d H:i') .'" )';
         } else {
           $lastRun = $localNow->subMinutes(10);
           $jql = '(' . $jqlBase . ') AND ( UPDATED >= "'. $lastRun->format("Y-m-d H:i") .'" )';
         }

         if($this->output->isVerbose())  
         {
           $this->warn("JQL: " . $jql);
         }

        $issues = app(Jira::class)->getIssues($jql);
        if(!$issues || count($issues) <= 0) 
        {
            $this->error("No issues match the tracker query");
            $issues = [];
        } else {
           $this->info(count($issues) . " match the tracker query");
        }

        foreach($issues as $issue)
        {
           $issueData = $issue->all()->only(['status', 'key','labels','issue type_name','assignee','summary','priority_name','due date','updated','created'])->toArray(); 
           $comments = app(Jira::class)->getIssueComments($issueData['key']);

           $this->info("Issue: " . $issueData['key']); 
           if($this->output->isVerbose())  
           {
             $this->info("Comment count: " . count($comments));
           }

           if(count($comments) > 0 && isset($tracker['comment-triggers']) && count($tracker['comment-triggers']) > 0) {
             foreach($tracker['comment-triggers'] as $commentTriggerId => $commentTrigger) {
               foreach($commentTrigger['triggers'] as $trigger) {
                 $this->info("Checking for new comments containing: " . $trigger);
                 foreach($comments as $comment) {
                   $commentData = $comment->all()->only(['body','updated'])->toArray();
                   if(strpos($commentData['body'], $trigger) !== false)
                   {
                     $commentTime = new Carbon($commentData['updated']);
                     if($commentTime->gt($lastRun))
                     {
                       $this->warn("Will fire notification for " . $commentTriggerId);
                     } else {
                       $this->warn($commentTime->toIso8601String() . " is not gt " . $lastRun->toIso8601String());
                     }
                   }
                 }
               }
             }
           }
        }

        $trackerData['lastrun'] = $localNow->toIso8601String();
        file_put_contents($trackerFile, json_encode($trackerData));
       }
    }
}
