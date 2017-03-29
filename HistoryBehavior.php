<?php

namespace boolean\history;

use yii;
use yii\base\Behavior;
use yii\base\Event;
use yii\db\ActiveRecord;
use yii\db\BaseActiveRecord;

/*
 * This behavior is used to store logs of updated/inserted/deleted models.
 */

class HistoryBehavior extends Behavior
{
    const EVENT_INSERT = 1;
    const EVENT_UPDATE = 2;
    const EVENT_DELETE = 3;

    public $skipAttributes = [];

    public $allowEvents = [
        self::EVENT_INSERT,
        self::EVENT_UPDATE,
        self::EVENT_DELETE,
    ];

    protected $eventMap = [
        self::EVENT_INSERT => BaseActiveRecord::EVENT_AFTER_INSERT,
        self::EVENT_UPDATE => BaseActiveRecord::EVENT_AFTER_UPDATE,
        self::EVENT_DELETE => BaseActiveRecord::EVENT_AFTER_DELETE,
    ];

    public function events()
    {
        $events = [];
        foreach ($this->allowEvents as $name) {
            $events[$this->eventMap[$name]] = 'saveHistory';
        }
        return $events;
    }

    public function init()
    {
        parent::init();
        $this->skipAttributes = array_fill_keys($this->skipAttributes, true);
    }

    public function saveHistory(Event $event)
    {
        $history = new HistoryEntity();
        $history->initiator = $this->getInitiator();
        $history->ip = $this->getRequestIP();
        $history->event = $event->name;
        $history->class = $event->sender->classname();
        $history->table_name = $this->getTableName();
        $history->row_id = $this->getRowId();

        $log = '';

        switch ($event->name) {
            case BaseActiveRecord::EVENT_AFTER_INSERT:

                $attributes = $this->processSkip($this->owner->attributes);
                $log['new'] = $attributes;

                break;
            case BaseActiveRecord::EVENT_AFTER_UPDATE:

                $oldAttributes = $this->processSkip($event->changedAttributes);
                $attributes = $this->owner->attributes;
                foreach ($oldAttributes as $key => $attribute) {
                    if ($oldAttributes[$key] != $attributes[$key]) {
                        $log['old'][$key] = $oldAttributes[$key];
                        $log['new'][$key] = $attributes[$key];
                    }
                }

                break;
            case BaseActiveRecord::EVENT_AFTER_DELETE:

                $log['old'] = $this->processSkip($this->owner->attributes);
                break;
        }

        if ($log) {
            $history->log = json_encode($log);
            $history->created_at = time();

            $history->save(false);
        }
    }

    protected function processSkip($attributes)
    {
        foreach ($attributes as $key => $value) {
            if (isset($this->skipAttributes[$key])) {
                unset($attributes[$key]);
            }
        }
        return $attributes;
    }

    protected function getInitiator()
    {
        if (isset(Yii::$app->user) && isset(Yii::$app->user->identity)) {
            return Yii::$app->user->getId();
        }
        return null;
    }

    protected function getRequestIP()
    {
        if (isset(Yii::$app->request) && isset(Yii::$app->request->userIP)) {
            return Yii::$app->request->userIP;
        }
        return null;
    }

    protected function getTableName()
    {
        /** @var ActiveRecord $model */
        $model = $this->owner;
        return $model->tableName();
    }

    protected function getRowId()
    {
        /** @var ActiveRecord $model */
        $model = $this->owner;
        return $model->primaryKey;
    }
}