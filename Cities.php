<?php
if (!function_exists('mb_ucfirst')) {
    function mb_ucfirst($string, $enc = 'UTF-8')
    {
        return mb_strtoupper(mb_substr($string, 0, 1, $enc), $enc) . mb_substr($string, 1, mb_strlen($string, $enc), $enc);
    }
}

class Cities
{
	private $db = NULL;
	private $chat = 0;
	//private $search     = mb_strtolower($_GET['name'], 'UTF-8'); //переводим в нижний регист ответ
	private $lines; //все города
	private $citiescount;
	private $used = array(); //использованные города
	//private $fnameUsed  = 'usedCity.txt'; //ссылка на исп города
	private $count1; //количество угаданных городов
	private $count; //     = $count1--; //приравниваем к числу
	private $otvet; //     = $used[$count1]; //текущий ответ
	private $NUJbuk; //    = $this->checkLetter($used[$count1]) ; //посл буква нужная ОНА В ФАЙЛЕ
	private $poslBukva;// = mb_substr($search, -1, 1, 'UTF-8'); //посл буква присланного овета
	private $pervBukva;//  = mb_substr($search, 0, 1, 'UTF-8'); //первая буква присланного ответа
	private $namedletters = array();//количество оставшихся городов на каждую букву
	function __construct($chatid)
	{
		require_once("config.php");
		mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
		if (!$this->chat = intval($chatid))
			die ("Не удалось получить идентификатор чата");
		$this->lines = file("goroda.txt");
		$this->citiescount = count($this->lines);
		if ($this->citiescount<1)
			die("База городов недоступна");
		for ($i=0; $i<$this->citiescount; $i++)
			$this->lines[$i] = mb_ucfirst(trim ($this->lines[$i]), 'UTF-8');
		try 
		{
     $this->db = new mysqli($my_host, $my_user, $my_password, $my_database) ;
     $this->db->set_charset('utf8');
     $this->db->query('SET NAMES utf8');
     
		} catch (Exception $e ) 
		{
				 die ("База данных недоступна");
		}
	}
	
	function __destruct()
	{
		$this->db->close();
	}
	
	private function checkLetter($word)
	{
		//проверка на буквы
		for ($i=mb_strlen($word, 'UTF-8'); $i>0; $i--)
		{
			$NOVbuk = mb_substr($word, $i-1, 1, 'UTF-8');
			if ($this->namedletters[mb_strtoupper($NOVbuk, 'UTF-8')]>0)
			{
				return $NOVbuk;
			}
		}
		return 'а';
	}
	
	public function getGame()
	{
		$game = false;
		try
		{
			$result = $this->db->query("SELECT * FROM cities WHERE chatid = $this->chat");
			//die ("privet");
			if ($result->num_rows > 0)
			{
				$res = $result->fetch_assoc();
				$this->namedletters = unserialize($res['letters']);
				$this->used = unserialize($res['named']);
				$this->count1 = count($this->used);
				$this->count = $this->count1--;
				$this->otvet = $this->used[$this->count1];
				$this->NUJbuk = mb_strtoupper($this->checkLetter($this->used[$this->count1]));
				$game = true;
			}
			$result->close();
		} catch (Exception $e ) 
		{
				 die ("База данных недоступна");	
		}
		return $game;
	}
	
	public function removeGame()
	{
		try
		{
			$this->db->query("DELETE FROM cities WHERE chatid = $this->chat");
		}
		catch (Exception $e ) 
		{
				 die ("База данных недоступна");	
		}
	}
	
	public function createGame()
	{
		//for ($i=ord('а'); $i<=ord('я'); $i++)
		//	$this->namedletters[$i]=0;
		//$this->namedletters=array_values($this->namedletters);
		$letters = array('А', 'Б', 'В', 'Г', 'Д', 'Е', 'Ё', 'Ж', 'З', 'И', 'Й', 'К', 'Л', 'М', 'Н', 'О', 'П', 'Р', 'С', 'Т', 'У', 'Ф', 'Х', 'Ц', 'Ч', 'Ш', 'Щ', 'Ъ', 'Ы', 'Ь', 'Э', 'Ю', 'Я');
		foreach ($letters as $letter)
		{
			$this->namedletters[$letter]=0;
		}
		foreach ($this->lines as $city)
		{
			$first = mb_substr($city, 0, 1, 'UTF-8');
			$last = mb_substr($city, -1, 1, 'UTF-8');
			if (!isset($this->namedletters[$first])||!isset($this->namedletters[$last]))
			{
				//throw
			}
			$this->namedletters[$first]++;
		}
		$this->used[0] = $this->lines[rand(0,$this->citiescount-1)];
		$this->namedletters[mb_substr($this->used[0], 0, 1, 'UTF-8')]--;
		$newletter = $this->checkLetter($this->used[0]);
		//die (var_dump($this->namedletters));
		try
		{
			$this->db->query("INSERT INTO cities SET chatid = $this->chat, named='".$this->db->real_escape_string(serialize($this->used))."', letters='".$this->db->real_escape_string(serialize($this->namedletters))."'");
		}
		 catch (Exception $e ) 
		{
				 die ("База данных недоступна");	
		}
		return "Начинаю новую игру: ".mb_ucfirst($this->used[0])."\n Назовите город на букву ".mb_strtoupper($newletter);
	}
	
	public function nextStep($turn)
	{
		if (!$this->getGame())
			return $this->createGame();
		/*$search     = mb_strtolower($_GET['name'], 'UTF-8'); //переводим в нижний регист ответ
		$lines      = file('goroda.txt'); //все города
		$used       = file('usedCity.txt'); //использованные города
		$fnameUsed  = 'usedCity.txt'; //ссылка на исп города
		$count1     = count($used); //количество угаданных городов
		$count      = $count1--; //приравниваем к числу
		//echo $count1;
		$otvet      = $used[$count1]; //текущий ответ
		$NUJbuk     = $this->checkLetter($used[$count1]) ; //посл буква нужная ОНА В ФАЙЛЕ
		$poslBukva  = mb_substr($search, -1, 1, 'UTF-8'); //посл буква присланного овета
		$pervBukva  = mb_substr($search, 0, 1, 'UTF-8'); //первая буква присланного ответа
		*/
		$search     = mb_strtolower($turn, 'UTF-8'); //переводим в нижний регист ответ
		$pervBukva  = mb_strtoupper(mb_substr($search, 0, 1, 'UTF-8'));
		foreach ($this->used as $us) {
			$us = mb_strtolower($us, 'UTF-8');
		    if (strpos($us, $search) !== false and mb_strlen($us, 'UTF-8') < mb_strlen($search, 'UTF-8') + 2) { //проверка был ли город
		        return 'Город '. mb_strtoupper($search, 'UTF-8'). ' уже называли! Назовите другой город на букву '. $this->NUJbuk;
		        //exit;
		    }
		}
		
		if (mb_strlen($search) <= 2) {
		    return 'Текущий город: '. mb_strtoupper($this->otvet, 'UTF-8'). ' Первая буква города должна быть '. $this->NUJbuk;
		    //exit;
		}
		
		$fo    = mb_strlen($search, 'UTF-8') - 1;
		$found = false;

		if (mb_strpos($this->NUJbuk, $pervBukva) !== false) //проверка совпадает ли первая буква с последней
		    {
		    foreach ($this->lines as $line) 
		    {
		    	$fndcity = trim($line);
		        if (mb_strtolower($fndcity) === mb_strtolower($search)) //если ответ совпал и это не часть слова
		            {
		            $found = true;
		            $this->count++;
		            if ($this->count >= 30) //если количество городов равно 30
		                {
		                $this->removeGame();
		                return "30-ый город назван. Игра окончена!";
		            	} else { //если городов меньше 30
		                //проверка на буквы ыыы ййй ььь ёёёё  цццц  ффффф щщщ цикл ОФР ФОР
		               	$NOVbuk = $this->checkLetter ($fndcity);
		               	$this->namedletters[mb_substr($fndcity, 0, 1, 'UTF-8')]--;
		               	$this->used[]=$fndcity;
		                } //konec while
		                try
										{
											$this->db->query("UPDATE cities SET named='".$this->db->real_escape_string(serialize($this->used))."', letters='".$this->db->real_escape_string(serialize($this->namedletters))."' WHERE chatid=$this->chat");
										}
										 catch (Exception $e ) 
										{
												 die ("База данных недоступна");	
										}
		                return 'Назван правильный город '. mb_strtoupper($fndcity, 'UTF-8'). '! Теперь нужно назвать город на букву '. mb_strtoupper($NOVbuk, 'UTF-8');
		                
		            }
		            //не в ИФ, потому что всё равно пишем кол-во, или нулевое, или нет
		           // break; //прерываем поиск
		        }
		    }
		    // If the text was not found, show a message
		    if (!$found)
		        return 'Такого города в России нет! Текущий город: '. mb_strtoupper($this->used[$this->count1], 'UTF-8'). ' Первая буква города должна быть '. $this->NUJbuk;
		}
}
//$cities = new Cities(1);
//$cities->removeGame();
//echo $cities->nextStep($_GET['name']);