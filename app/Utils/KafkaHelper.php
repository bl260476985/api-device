<?php

namespace App\Utils;

use Illuminate\Support\Facades\Config;
use RdKafka;

class KafkaHelper
{

    /**
     * get an instance
     * @return \App\Utils\KafkaHelper
     */
    public static function instance()
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new KafkaHelper();
        }
        return $instance;
    }

    /**
     * search
     * @param   $req
     * @return
     */
    public function product($topic = 'test', $content, $device_number)
    {
        logger(":kafka=" . RD_KAFKA_PARTITION_UA);
        try {
            $conf = new RdKafka\Conf();
            $rk = new RdKafka\Producer($conf);
            $rk->addBrokers(env('ANYUE_KAFKA_HOST', '121.42.230.150'));

            $topicConf = new RdKafka\TopicConf();
            $topicConf->set('auto.commit.interval.ms', 100);

            // Set the offset store method to 'file'
            $topicConf->set('offset.store.method', 'file');
            $topicConf->set('offset.store.path', sys_get_temp_dir());
            $topicConf->set('auto.offset.reset', 'smallest');


            $topic = $rk->newTopic($topic, $topicConf);

            $res = $topic->produce(RD_KAFKA_PARTITION_UA, 0, $content, $device_number);
            logger("res debug:kafka=" . $res);
        } catch (\Exception $e) {
            logger("err debug:kafka=" . $e->getMessage());
            return false;
        }

        return true;

    }

    /**
     * search
     * @param  Request $req
     * @return
     */
    public function custom($topic = 'test')
    {
        try {
            $conf = new RdKafka\Conf();

            $rk = new RdKafka\Consumer($conf);
            $rk->addBrokers(env('ANYUE_KAFKA_HOST', '121.42.230.150'));

            $topicConf = new RdKafka\TopicConf();
            $topicConf->set('auto.commit.interval.ms', 100);

            $topicConf->set('offset.store.method', 'file');
            $topicConf->set('offset.store.path', sys_get_temp_dir());

// Alternatively, set the offset store method to 'broker'
// $topicConf->set('offset.store.method', 'broker');

// Set where to start consuming messages when there is no initial offset in
// offset store or the desired offset is out of range.
// 'smallest': start from the beginning
            $topicConf->set('auto.offset.reset', 'smallest');

            $topic = $rk->newTopic("testkyu", $topicConf);

            // Start consuming partition 0
            $topic->consumeStart(0, RD_KAFKA_OFFSET_STORED);

            //$topic->consumeStart(0, RD_KAFKA_OFFSET_BEGINNING);

            /*
            while (true) {
                        $message = $topic->consume(0, 2000);
                             echo $message->err, "\n";
                            switch ($message->err) {
                                    case RD_KAFKA_RESP_ERR_NO_ERROR:
                                            //var_dump($message);
                                             echo $msg->errstr(), "\n";
                                            break;
                                    case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                                            echo "No more messages; will wait for more\n";
                                            break;
                                    case RD_KAFKA_RESP_ERR__TIMED_OUT:
                                            echo "Timed out\n";
                                            break;
                                    default:
                                            throw new \Exception($message->errstr(), $message->err);
                                    break;
                  }
            }
            */

            while (true) {
                $msg = $topic->consume(0, 1000);
                if ($msg->err) {
                    echo $msg->errstr(), "\n";
                    break;
                } else {
                    echo $msg->payload, "\n";
                }

                $topic->offsetStore($msg->partition, $msg->offset);

            }

        } catch (\Exception $e) {
            Log::info("err debug consume:kafka=" . $e->getMessage());
            return false;
        }
        return true;

    }


}
