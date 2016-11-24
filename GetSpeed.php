<?php
namespace Yii2Resque\api;

/**
 * file GetSpeed.php
 * --------------------------------------------------------------------------------------- +
 * @abstract：简单的程序开销记录脚本，可以记录程序分步执行效率，可以将结果入库；
 *	- 返回数据中包括：每项消耗、总消耗、平均消耗、统计标题和每项别名、便于生成SQL;
 *	- 可用于生成统计报表、分析慢程序；
 *	- 支持自定义标识、默认标识；
 *	- 可以结合队列，将结算的开销加入队列处理；
 * @author <http://github.com/wangcun>
 * @edit date 2016/11/21
 * @return string|array	
 * @usage: 
 *	$speedDemo	= new GetSpeed('DEMO[xxxxxxxxxxxx-1844767]');
 *	usleep(rand());
 *	$speedDemo->autoLop('第1次');
 *	usleep(rand());
 *	$speedDemo->autoLop('第2次');
 *	usleep(rand());
 *	sleep(2);
 *	$speedDemo->autoLop('第3次');
 *	usleep(rand());
 *	$speedDemo->autoLop('第4次');
 *	usleep(rand());
 *	$speedDemo->autoLop('第5次');
 *	usleep(130089);
 *	$speedDemo->end();
 *
 *	echo $speedDemo->getData(); 
 *	print_r($speedDemo->getData('arr'));
 *	
 *	【输出结果】
 *	a:8:{s:5:"title";s:49:"DEMO[xxxxxxxxxxxx-1844767]";s:5:"alias";a:7:{i:0;s:5:"Start";i:1;s:5:"第1次";i:2;s:5:"第2次";i:3;s:5:"第3次";i:4;s:5:"第4次";i:5;s:5:"第5次";i:6;s:3:"End";}s:6:"option";a:7:{i:0;s:21:"Option-20150820053831";i:1;s:21:"Option-20150820053831";i:2;s:21:"Option-20150820053831";i:3;s:21:"Option-20150820053833";i:4;s:21:"Option-20150820053833";i:5;s:21:"Option-20150820053833";i:6;s:21:"Option-20150820053833";}s:4:"time";a:7:{i:0;d:1440063511.6572399;i:1;d:1440063511.6612401;i:2;d:1440063511.694242;i:3;d:1440063513.7133579;i:4;d:1440063513.738359;i:5;d:1440063513.74736;i:6;d:1440063513.8783669;}s:8:"allSpeed";d:2.2211270332336426;s:7:"average";d:0.31730386189052034;s:10:"optionName";s:29:"第1次,第2次,第3次,第4次,第5次";s:11:"optionSpeed";s:105:"0.004000186920166,0.033001899719238,2.0191159248352,0.025001049041748,0.0090010166168213,0.13100695610046";}
 *	Array
 *	(
 *		[title] => DEMO[xxxxxxxxxxxx-1844767]
 *		[alias] => Array
 *			(
 *				[0] => Start
 *				[1] => 第1次
 *				[2] => 第2次
 *				[3] => 第3次
 *				[4] => 第4次
 *				[5] => 第5次
 *				[6] => End
 *			)
 *
 *		[option] => Array
 *			(
 *				[0] => Option-20150820053831
 *				[1] => Option-20150820053831
 *				[2] => Option-20150820053831
 *				[3] => Option-20150820053833
 *				[4] => Option-20150820053833
 *				[5] => Option-20150820053833
 *				[6] => Option-20150820053833
 *			)
 *
 *		[time] => Array
 *			(
 *				[0] => 1440063511.6572
 *				[1] => 1440063511.6612
 *				[2] => 1440063511.6942
 *				[3] => 1440063513.7134
 *				[4] => 1440063513.7384
 *				[5] => 1440063513.7474
 *				[6] => 1440063513.8784
 *			)
 *
 *		[allSpeed] => 2.2211270332336
 *		[average] => 0.31730386189052
 *		[optionName] => 第1次,第2次,第3次,第4次,第5次
 *		[optionSpeed] => 0.004000186920166,0.033001899719238,2.0191159248352,0.025001049041748,0.0090010166168213,0.13100695610046
 *	)
 * @since 1.1
 */

class GetSpeed
{

	private $speedData;
	
	public function __construct($name = NULL)
	{
		$this->speedData = [
			'title'		=> (empty($name) ? 'Get Speed-' . date('Ymdhis') : $name),
			'alias'		=> [],
			'option'	=> [],
			'time'		=> [],
			'allSpeed'	=> 0,
			'average'	=> 0,
		];
		
		$this->autoLop('Start');
	}
	
	/**
	 * 获取时间
	 */
	protected function getMicrotime()
	{
		list($usec,  $sec)	= explode(" ",  microtime());
		return ((float) $usec + (float) $sec);
	}
	
	protected function getDiff($a, $b)
	{
		return (float) ($b - $a);
	}
	
	protected function add($name)
	{
		$this->speedData['alias'][]		= (empty($name) ? uniqid('Alias-') : $name);
		$this->speedData['option'][]	= uniqid('Option-');
		$this->speedData['time'][]		= $this->getMicrotime();
	}
	
	protected function getAllSpeed()
	{
		$data	= [];
		$tmp	= $this->speedData['time'];
		
		if (! empty($tmp)) {
			$data['all']	= $this->getDiff($tmp[0], end($tmp));
			$data['optionSpeed']	= [];
			
			for ($i = 1; $i < count($tmp); $i++) {
				$curr	= $tmp[$i];
				$pre	= $tmp[$i -1];
				$data['optionSpeed'][] = $this->getDiff($pre, $curr);
			}
			
			$data['optionSpeedStr']	= join($data['optionSpeed'], ",");
			unset($data['optionSpeed']);
		}
		return $data;
	}
	
	protected function getOptionStr()
	{
		if (! empty($this->speedData['alias'])) {
			$tmp	= $this->speedData['alias'];
			array_pop($tmp);
			array_shift($tmp);
			return join($tmp, ",");
		} else {
			return ;
		}
	}
	
	/**
	 * 执行记录动作
	 */
	public function autoLop($name=NULL)
	{
		$this->add($name);
	}
	
	/**
	 * 结束所有行为，开始计算开销
	 */
	public function end()
	{
		$this->autoLop('End');
		$allSpeed	= $this->getAllSpeed();
		$average	= (float)($allSpeed['all'] / count($this->speedData['option']));
		
		$this->speedData['optionName']	= $this->getOptionStr();
		$this->speedData['optionSpeed']	= $allSpeed['optionSpeedStr'];
		$this->speedData['allSpeed']	= $allSpeed['all'];
		$this->speedData['average']		= $average;
	}
	
	/**
	 * 可以获取所有开销数据，形式可以是字符、数组
	 */
	public function getData($t = 'str')
	{
		if ($t == "arr") {
			return $this->speedData;
		} else {
			return serialize($this->speedData);
		}
	}
}