<?php
 namespace Yii2Resque\api;

/**
 * file msgqueue.php
 *
 * @abstract 配合resque使用的扩展类，用于不同服务器不同项目不安装resque的情况下，直接追加数据。
 *	- 支持一次实例多次追加；
 *	- 支持自定义的命名空间；
 *	- 数据入队列前后有数据回调可用；
 *
 * @date 2015/04/21
 * @edited Date 2017/08/07
 * @author <http://github.com/wangcun>
 * @usage: 
 *
 * @since 2.1
 */
 
class MsgQueue
{

    private $redis;		//用于接收redis实例
    private $queueList;	 //所有队列的列表
    private $res;		//结果字符串
    private $queueName;	//queueName
    private $jobName;	//job class Name
	private $prefix;	//Redis key的公共前缀
	private $mointer;	// 监控
	
	
	/**
	 * 初始化队列
	 * @params $jobName string ，job类的名称
	 * @params $queueName string ，队列的名称
	 * @params $redis objct， redis对象
	 * @params $namespace	队列的前缀，默认值不用修改
	 * @params $mointer 是否开启监控，默认开启
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
	 * 可设置Redis对象
	 */
    public function setRedis($redis)
    {
        $this->redis    = $redis;
    }
	
	
	/**
	 * 可设置队列名称
	 */
    public function setQueueList($queueName)
    {
		$this->queueList	= $this->prefix . "queues";
		$this->queueName	= $this->prefix . "queue:" . $queueName;
		
        $this->redis->sadd($this->queueList, $queueName);
    }
	
	
	/**
	 * 格式化入库数据，传的参数同步resque
	 */
    public function initJobData($data)
    {
        if (! empty($data)) {
			return [
				'class'	=> $this->jobName,
				'args'	=> [$data],
				'id'	=> time(),
				'queue_time' => microtime(true)
			];
        }
        return;
    }

    
	/**
	 * 将数据加入队列
	 * @params $data
	 * @params $callpre Hooks，用于在数据加入队列动作之前执行，可以是匿名函数或是指定类中方法。
	 * @params $callback Hooks，用于在数据加入队列动作之后执行，可以是匿名函数或是指定类中方法。
	 * @return string
	 */
    public function add2Queue($data)
    {
		$handle_data    = $data;
		if (isset($handle_data['callpre']) && is_array($handle_data['callpre'])) {
			$this->runCallback($handle_data['callpre']);
		}

		$id		= (string) $this->generateJobId();
		$data	= $this->initJobData($data);
		$data['id']	= $id;
		$data	= json_encode($data);
		if ($data === false)  return false;
        $this->redis->rPush($this->queueName, $data);
		$this->doMointer($id);

		if (isset($handle_data['callback']) && is_array($handle_data['callback'])) {
			$this->runCallback($handle_data['callback']);
		}

		unset($handle_data);
        return $data;
    }

	
	/**
	 * 执行回调函数
	 */
	protected function runCallback($pack)
	{
		if (! empty($pack) && is_array($pack)) {

			try {
				list ($class, $sArgs)    = $pack;
				if (is_callable($class)) call_user_func_array($class, [$sArgs]);
			} catch (Exception $e) {}

			unset($class, $sArgs, $pack);
		}
	}
    

	/**
	 * 对入库的数据追加状态监控
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
	 * 返回一串32位的字符串，同步resque Worker.php中的方法
	 */
	public function generateJobId()
	{
		return md5(uniqid('', true));
	}
}
