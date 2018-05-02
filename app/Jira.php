<?php namespace App;

use stdClass;
use chobie\Jira\Api;
use chobie\Jira\Api\Authentication\Basic;
use chobie\Jira\Issues\Walker;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Translation\Exception\NotFoundResourceException;
use App\JiraUtils;

class Jira
{
    use JiraUtils\JiraConfigTrait;


    protected $jiraUrl;

    protected $jiraUsername;

    protected $jiraPassword;

    protected $api;

    public function __construct($jiraUrl, $jiraUsername, $jiraPassword)
    {
        $this->jiraUrl = $jiraUrl;
        $this->jiraUsername = $jiraUsername;
        $this->jiraPassword = $jiraPassword;
    }

    public function getApi()
    {
        return new \chobie\Jira\Api(
            $this->jiraUrl,
            new \chobie\Jira\Api\Authentication\Basic($this->jiraUsername,
            $this->jiraPassword)
        );
    }

    public function getTransitionStates($issueKey)
    {
        // Get available transitions.
        $api = $this->getApi();
        $tmp_transitions = $api->getTransitions($issueKey, []);
        $tmp_transitions_result = $tmp_transitions->getResult();
        $transitions = $tmp_transitions_result['transitions'];
        return $transitions;
    }

    public function editIssue(
        $issueKey,
        stdClass $object = null,
        array $fields = []
    ) {
        $api = $this->getApi();
        $updates = [];
        if ($object) {
            $updates['update'] = $object;
        }

        if (count($fields)) {
            $updates['fields'] = $fields;
        }

        $r = $api->editIssue($issueKey, $updates);
        if (is_object($r)) {
            $res = $r->getResult();
            if (isset($res['errors'])) {
                $error = "";
                foreach ($res['errors'] as $k => $v) {
                    $error .= $k . ": " . $v . " ";
                }

                throw new \Exception($error);
            }
        }

        return $r;
    }

    public function transitionIssue($issueKey, $targetState)
    {
        $api = $this->getApi();
        $result = $api->transition(
            $issueKey,
            [
                'transition' => ['id' => $targetState],
            ]
        );

        return ($result);
    }

    public function getIssueWorklogs($issue)
    {
        $api = $this->getApi();
        $result = $api->getWorklogs($issue, [])->getResult();

        if (!isset($result['worklogs'])) {
            return [];
        }

        $rawWorkLogs = $result['worklogs'];
        $workLogs = [];

        foreach ($rawWorkLogs as $wl) {
            $workLog = app(Worklog::class);
            $workLog->fill($issue, $wl);

            $workLogs[] = $workLog;
        }

        return $workLogs;
    }

    public function getIssueComments($issueKey)
    {
        $comments = [];

        $issue = $this->getIssue($issueKey, '');
        $rawData = $issue->all()->only(['comment'])->toArray();
        if (!isset($rawData['comment']) || !isset($rawData['comment']['comments'])) {
            return [];
        }

        $rawComments = $rawData['comment'];

        foreach ($rawComments['comments'] as $cm) {
            $comment = app(Comment::class);
            $comment->fill($issueKey, $cm);

            $comments[] = $comment;
        }

        return $comments;
    }

    public function getIssues($query)
    {

        $api = $this->getApi();

        $walker = new Walker($api);
        $walker->push($query);

        $issues = [];
        foreach ($walker as $jiraIssue) {
            $issue = app(Issue::class);
            $issue->fill($jiraIssue->getKey(), '', $jiraIssue);
            $issues[] = $issue;
        }

        return $issues;
    }

    public function getIssue($issueKey)
    {
        $issue = app(Issue::class);
        $issue->fill($issueKey, '');
        if (!$issue || $issue->isEmpty()) {
            return null;
        }

        return $issue;
    }

    public function setIssueWatchers($issue, array $watchers)
    {
        $api = $this->getApi();
        return $api->setWatchers($issue, $watchers);
    }

    public function unsetIssueWatchers($issue, array $watchers)
    {
        $api = $this->getApi();
        $result = array();

        foreach ($watchers as $watcher) {
            $result[] = $api->api(Api::REQUEST_DELETE, sprintf('/rest/api/2/issue/%s/watchers?username=' . $watcher, $issue));
        }

        return $result;
    }


    public function getIssueWatchers($issue)
    {
        $api = $this->getApi();
        $rawWatchers = $api->api(Api::REQUEST_GET, sprintf('/rest/api/2/issue/%s/watchers', $issue))
            ->getResult();

        if (isset($rawWatchers['errorMessages'])) {
            return [];
        }

        return $rawWatchers;
    }

    public function getCurrentUserData()
    {
        $api = $this->getApi();
        $rawUser = $api->api(Api::REQUEST_GET, '/rest/auth/latest/session')
            ->getResult();

        if (isset($rawUser['errorMessages'])) {
            return [];
        }

        return $rawUser;
    }

    public function getTempoUserWorklogs($user = null, $from = null, $to = null)
    {
        $params = [];
        if ($user) {
            $params['username'] = $user;
        }
        if ($from) {
            $params['dateFrom'] = $from;
        }
        if ($to) {
            $params['dateTo'] = $to;
        }

        $api = $this->getApi();
        $rawWorkLogs = $api->api(Api::REQUEST_GET,
            '/rest/tempo-timesheets/3/worklogs', $params, true, false, false);

        if (isset($rawWorkLogs['errorMessages'])) {
            return [];
        }

        $workLogs = [];

        foreach ($rawWorkLogs as $wl) {
            $issue = $wl['issue']['key'];

            $workLog = app(Worklog::class);
            $workLog->fill($issue, $wl);

            $workLogs[] = $workLog;
        }

        return $workLogs;
    }

    public function addComment($issue, $comment)
    {
        $params = [];
        if ($comment) {
            $params['body'] = $comment;
        }

        $api = $this->getApi();
        $ret = $api->api(Api::REQUEST_POST,
            '/rest/api/2/issue/' . $issue . '/comment', $params);

        return $ret;
    }

    public function addWorklog($issue, $timeSpent, $date, $comment)
    {
        $params = [];
        if ($issue) {
            $params['issueId'] = $issue;
        }
        if ($date) {
            $params['started'] = $date;
        }
        if ($timeSpent) {
            $params['timeSpent'] = $timeSpent;
        }
        if ($comment) {
            $params['comment'] = $comment;
        }

        $api = $this->getApi();
        return $api->api(Api::REQUEST_POST,
            '/rest/api/2/issue/' . $issue . '/worklog', $params);
    }

    public function getIssueQuery($queryName)
    {
        $configArray = self::resolveConfigArray();
        if (isset($configArray['queries']) && isset($configArray['queries'][$queryName])) {
            return $configArray['queries'][$queryName];
        }

        return null;
    }

    public function getUsername($userName)
    {
        $configArray = self::resolveConfigArray();
        if (isset($configArray['users']) && isset($configArray['users'][$userName])) {
            return $configArray['users'][$userName];
        }

        return null;
    }

    public function getIssueKey($queryKey)
    {
        $configArray = self::resolveConfigArray();
        if (isset($configArray['issues']) && isset($configArray['issues'][$queryKey])) {
            return $configArray['issues'][$queryKey];
        }

        return null;
    }

    public function createIssue(
        $projectKey,
        $summary,
        $type,
        $description = null,
        $assign = null,
        array $labels,
        array $components
    ) {
        $api = $this->getApi();

        $params = [
            'fields' => [
                'project' => [
                    'key' => $projectKey,
                ],
                'summary' => $summary,
                'issuetype' => [
                    "name" => $type,
                ],
            ],
        ];

        if ($description) {
            $params['fields']['description'] = $description;
        }

        if ($assign) {
            $params['fields']['assignee']['name'] = $assign;
        }

        if (count($labels)) {
            $params['fields']['labels'] = $labels;
        }

        if (count($components)) {
            $params['fields']['components'] = [];
            foreach ($components as $component) {
                $params['fields']['components'][] = [
                    'name' => $component,
                ];
            }
        }

        return $api->api(Api::REQUEST_POST, '/rest/api/2/issue/', $params);
    }

    public function getServerInfo()
    {
        $api = $this->getApi();
        $result = $api->api(Api::REQUEST_GET, '/rest/api/2/serverInfo/', []);
        $raw = $result->getResult();
        return $raw;
    }

    public function getServerTime()
    {
        $info = $this->getServerInfo();
        return $info['serverTime'] ?? '';
    }

    public function getPriorities()
    {
        $api = $this->getApi();
        $result = $api->api(Api::REQUEST_GET, '/rest/api/2/priority/', []);
        $raw = $result->getResult();
        return $raw;
    }

    public function getPrioritiesList()
    {
        $raw = $this->getPriorities();
        $ret = [];

        foreach($raw as $r) {
            $ret[$r['id']] = $r['name'];
        }

        return $ret;
    }
}
