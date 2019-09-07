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
    private $lines; // cities list
    private $citiescount;
    private $used = array(); // used cities
    private $count1; // used cities count
    private $count; //     = $count1--;
    private $current; // current city
    private $curletter; //    current letter
    private $namedletters = array(); // number of remaining cities per letter
    function __construct($chatid)
    {
        require_once("config.php");
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        if (!$this->chat = intval($chatid))
            throw new Exception('Invalid chat id');
        $this->lines       = file("goroda.txt");
        $this->citiescount = count($this->lines);
        if ($this->citiescount < 1)
             throw new Exception('DB problems');
        for ($i = 0; $i < $this->citiescount; $i++)
            $this->lines[$i] = mb_ucfirst(trim($this->lines[$i]), 'UTF-8');
        try {
            $this->db = new mysqli($my_host, $my_user, $my_password, $my_database);
            $this->db->set_charset('utf8');
            $this->db->query('SET NAMES utf8');
            
        }
        catch (Exception $e) {
            throw new Exception('DB problems');
        }
    }
    
    function __destruct()
    {
        $this->db->close();
    }
    
    private function checkLetter($word)
    {
        for ($i = mb_strlen($word, 'UTF-8'); $i > 0; $i--) {
            $letter = mb_substr($word, $i - 1, 1, 'UTF-8');
            if ($this->namedletters[mb_strtoupper($letter, 'UTF-8')] > 0) {
                return $letter;
            }
        }
        return 'а';
    }
    
    public function getGame()
    {
        $game = false;
        try {
            $result = $this->db->query("SELECT * FROM cities WHERE chatid = $this->chat");
            if ($result->num_rows > 0) {
                $res                = $result->fetch_assoc();
                $this->namedletters = unserialize($res['letters']);
                $this->used         = unserialize($res['named']);
                $this->count1       = count($this->used);
                $this->count        = $this->count1--;
                $this->current      = $this->used[$this->count1];
                $this->current      = mb_strtoupper($this->checkLetter($this->used[$this->count1]));
                $game               = true;
            }
            $result->close();
        }
        catch (Exception $e) {
            throw new Exception('DB problems');
        }
        return $game;
    }
    
    public function removeGame()
    {
        try {
            $this->db->query("DELETE FROM cities WHERE chatid = $this->chat");
        }
        catch (Exception $e) {
            throw new Exception('DB problems');
        }
    }
    
    public function createGame()
    {
        $letters = array('А', 'Б', 'В', 'Г', 'Д', 'Е', 'Ё', 'Ж', 'З', 'И', 'Й', 'К', 'Л', 'М', 'Н', 'О', 'П', 'Р', 'С', 'Т', 'У', 'Ф', 'Х', 'Ц', 'Ч', 'Ш', 'Щ', 'Ъ', 'Ы', 'Ь', 'Э', 'Ю', 'Я');
        foreach ($letters as $letter) {
            $this->namedletters[$letter] = 0;
        }
        foreach ($this->lines as $city) {
            $first = mb_substr($city, 0, 1, 'UTF-8');
            $last  = mb_strtoupper(mb_substr($city, -1, 1, 'UTF-8'));
            if (!isset($this->namedletters[$first]) || !isset($this->namedletters[$last])) {
                throw new Exception('Invalid cities list');
            }
            $this->namedletters[$first]++;
        }
        $this->used[0] = $this->lines[rand(0, $this->citiescount - 1)];
        $this->namedletters[mb_substr($this->used[0], 0, 1, 'UTF-8')]--;
        $newletter = $this->checkLetter($this->used[0]);
        try {
            $this->db->query("INSERT INTO cities SET chatid = $this->chat, named='" . $this->db->real_escape_string(serialize($this->used)) . "', letters='" . $this->db->real_escape_string(serialize($this->namedletters)) . "'");
        }
        catch (Exception $e) {
            throw new Exception('DB problems');
        }
        return "Начинаю новую игру: " . mb_ucfirst($this->used[0]) . "\n Назовите город на букву " . mb_strtoupper($newletter);
    }
    
    public function nextStep($turn)
    {
        if (!$this->getGame())
            return $this->createGame();
        $search      = mb_strtolower($turn, 'UTF-8');
        $firstletter = mb_strtoupper(mb_substr($search, 0, 1, 'UTF-8'));
        foreach ($this->used as $us) {
            $us = mb_strtolower($us, 'UTF-8');
            if (strpos($us, $search) !== false and mb_strlen($us, 'UTF-8') < mb_strlen($search, 'UTF-8') + 2) { //check if this city is already named 
                return 'Город ' . mb_strtoupper($search, 'UTF-8') . ' уже называли! Назовите другой город на букву ' . $this->current;
            }
        }
        
        if (mb_strlen($search) <= 2) {
            return 'Текущий город: ' . mb_strtoupper($this->current, 'UTF-8') . ' Первая буква города должна быть ' . $this->current;
        }
        
        $fo    = mb_strlen($search, 'UTF-8') - 1;
        $found = false;
        
        if (mb_strpos($this->current, $firstletter) !== false) //check if the letter is correct
            {
            foreach ($this->lines as $line) {
                $fndcity = trim($line);
                if (mb_strtolower($fndcity) === mb_strtolower($search)) //if the city exists
                    {
                    $found = true;
                    $this->count++;
                    if ($this->count >= 30) {
                        $this->removeGame();
                        return "30-ый город назван. Игра окончена!";
                    } else {
                        $newletter = $this->checkLetter($fndcity);
                        $this->namedletters[mb_substr($fndcity, 0, 1, 'UTF-8')]--;
                        $this->used[] = $fndcity;
                    }
                    try {
                        $this->db->query("UPDATE cities SET named='" . $this->db->real_escape_string(serialize($this->used)) . "', letters='" . $this->db->real_escape_string(serialize($this->namedletters)) . "' WHERE chatid=$this->chat");
                    }
                    catch (Exception $e) {
                        throw new Exception('DB problems');
                    }
                    return 'Назван правильный город ' . mb_strtoupper($fndcity, 'UTF-8') . '! Теперь нужно назвать город на букву ' . mb_strtoupper($newletter, 'UTF-8');
                    
                }
            }
        }
        // If the city was not found, show a message
        if (!$found)
            return 'Такого города в России нет! Текущий город: ' . mb_strtoupper($this->used[$this->count1], 'UTF-8') . ' Первая буква города должна быть ' . $this->current;
    }
}
