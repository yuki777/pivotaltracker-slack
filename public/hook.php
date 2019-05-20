<?php
require __DIR__ . '/../vendor/autoload.php';

class Hook
{
    public function __construct()
    {
        $dotenv = Dotenv\Dotenv::create(__DIR__ . '/../');
        $dotenv->load();
    }

    public function run()
    {
        $activity = json_decode(file_get_contents("php://input"), true);

        if(! $this->validate($activity)) return false;

        $this->sendSlack($this->getSummary($activity));
    }

    private function validate($activity)
    {
        if(! isset($activity['kind'])) return false;
        if( ($activity['kind'] != 'story_create_activity')
        and ($activity['kind'] != 'story_update_activity')
        and ($activity['kind'] != 'task_create_activity')
        and ($activity['kind'] != 'task_update_activity')
        and ($activity['kind'] != 'task_delete_activity')
        and ($activity['kind'] != 'comment_create_activity')
        ) return false;

        if(! isset($activity['message'])) return false;
        if(! isset($activity['highlight'])) return false;

        if(! isset($activity['primary_resources'][0]['name'])) return false;
        if(! isset($activity['primary_resources'][0]['story_type'])) return false;
        if(! isset($activity['primary_resources'][0]['url'])) return false;

        if(! isset($activity['performed_by']['name'])) return false;

        return true;
    }

    private function getSummary($activity)
    {
        $summary = [
            'user'       => $activity['performed_by']['name'],
            'message'    => $activity['message'],
            'highlight'  => $activity['highlight'],
            'story_name' => $activity['primary_resources'][0]['name'],
            'story_type' => $activity['primary_resources'][0]['story_type'],
            'story_url'  => $activity['primary_resources'][0]['url'],
        ];

        if(isset($activity['changes'][0]['new_values']['description'])){
            $summary['description'] = $activity['changes'][0]['new_values']['description'];
        }

        return $summary;
    }

    private function sendSlack($summary)
    {
        $settings = ['link_names' => true];
        $client = new Nexy\Slack\Client($_ENV['SLACK_WEBHOOK_URL'], $settings);
        $fields = [
            new \Nexy\Slack\AttachmentField('User', $summary['user'], true),
            new \Nexy\Slack\AttachmentField('Highlight', $summary['highlight'], true),
            new \Nexy\Slack\AttachmentField('Story Name', $summary['story_name']),
            new \Nexy\Slack\AttachmentField('Message', $summary['message']),
        ];

        if(isset($summary['description'])){
            $fields[] = new \Nexy\Slack\AttachmentField('Description', $summary['description']);
        }

        return $client->attach((new \Nexy\Slack\Attachment())->setFields($fields))->send($summary['story_url']);
    }
}
(new Hook)->run();
