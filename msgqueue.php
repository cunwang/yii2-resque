<?php
 namespace Yii2Resque\api;

/**
 * file msgqueue.php
 *
 * @abstract ���resqueʹ�õ���չ�࣬���ڲ�ͬ��������ͬ��Ŀ����װresque������£�ֱ��׷�����ݡ�
 *	- ֧��һ��ʵ�����׷�ӣ�
 *	- ֧���Զ���������ռ䣻
 *	- ���������ǰ�������ݻص����ã�
 * @date 2015/04/21
 * @edited Date 2016/11/18
 * @author <http://github.com/wangcun>
 * @usage: 
 *
 * @since 2.0
 */
 
class MsgQueue
{

    private $redis;		//���ڽ���redisʵ��
    private $queueList;	 //���ж��е��б�
    private $res;		//����ַ���
    private $queueName;	//queueName
    private $jobName;	//job class Name
	private $prefix;	//Redis key�Ĺ���ǰ׺
	private $mointer;	// ���
	
	
	/**
	 * ��ʼ������
	 * @params $jobName string ��job�������
	 * @params $queueName string �����е�����
	 * @params $redis objct�� redis����
	 * @params $namespace	���е�ǰ׺��Ĭ��ֵ�����޸�
	 * @params $mointer �Ƿ�����أ�Ĭ�Ͽ���
	 */
    public function __construct($jobName, $queueName, & $redis, $prefix = 'resque:', $mointer = true)
    {
		if (empty($redis) || empty($jobName) || empty($queueName)) {
			return;
		}
		
        $this->jobName	= $jobName;
		$this->prefix	= (string) $prefix;
		$this->mointer	= (bool) $mointer;
		
        $this->setRedis($redis);
        $this->setQueueList($queueName);
    }
	
	
	/**
	 * ������Redis����
	 */
    public function setRedis($redis)
    {
        $this->redis    = $redis;
    }
	
	
	/**
	 * �����ö�������
	 */
    public function setQueueList($queueName)
    {
		$this->queueList	= $this->prefix . "queues";
		$this->queueName	= $this->prefix . "queue:" . $queueName;
		
        $this->redis->sadd($this->queueList, $queueName);
    }
	
	
	/**
	 * ��ʽ��������ݣ����Ĳ���ͬ��resque
	 */
    public function initJobData($data)
    {
        if (! empty($data)) {
			return [
				'class'	=> $this->jobName,
				'args'	=> [self::encoding($data)],
				'id'	=> time(),
				'queue_time' => microtime(true)
			];
        }
        return;
    }

    
	/**
	 * �����ݼ������
	 * @params $data
	 * @params $callpre Hooks�����������ݼ�����ж���֮ǰִ�У�������������������ָ�����з�����
	 * @params $callback Hooks�����������ݼ�����ж���֮��ִ�У�������������������ָ�����з�����
	 * @return string
	 */
    public function add2Queue($data)
    {
		$args	= func_get_args();
		array_shift($args);
		
		$callper	= array_shift($args);
		if (! empty($callper)) {
			try {
				list ($class, $sArgs)	= $callper;
				if (is_callable($class)) {
					call_user_func_array($class, $sArgs);
				}
			} catch (Exception $e) {
			}
		}
	
		//start
		$id		= (string) $this->generateJobId();
		$data	= $this->initJobData($data);
		$data['id']	= $id;
		$data	= json_encode($data);
		if ($data === false) {
			return false;
		}

        $this->redis->rPush($this->queueName, $data);
		$this->doMointer($id);
		//End

		$callback	= array_shift($args);
		if (! empty($callback)) {
			try {
				list ($class, $sArgs)	= $callback;
				if (is_callable($class)) {
					call_user_func_array($class, $sArgs);
				}
			} catch (Exception $e) {
			}
		}

        return $data;
    }
    

	/**
	 * ����������׷��״̬���
	 * @params $id string 
	 */
	protected function doMointer($id)
	{
		if (! empty($id) && $this->mointer) {
			$statusPacket = [
				'status'	=> 1,
				'updated' 	=> time(),
				'started' 	=> time(),
			];
			$this->redis->set($this->prefix . 'job:' . $id . ':status', json_encode($statusPacket));
		}
	}
	
	/**
	 * ����һ��32λ���ַ�����ͬ��resque Worker.php�еķ���
	 */
	public function generateJobId()
	{
		return md5(uniqid('', true));
	}
	
	
	/**
	 * ���߷���
	 */
    static public function encoding($data, $input='gbk', $out='utf-8')
    {
        if (is_array($data)) {
            foreach($data as $key => $value) {
                $data[$key] = self::encoding($value, $input, $out);
            }
            return $data;
        } else {
            return iconv("{$input}", "{$out}//IGNORE", $data);
        }
    }
}
