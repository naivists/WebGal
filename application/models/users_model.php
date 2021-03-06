<?php

class users_model extends CI_Model {

    public function __construct() {
	parent::__construct();
	$this->load->database();
    }

//
//iekomentētais kods ir strādājošs, bet
//nav nepieciešams 2011. gada TTII kursā
//
//#########################################################
//    //vienkāršā autentifikācija, kur 
//    //tiešā veidā salīdzina iesūtītā parole <-> db parole 
//    public function simple_password($user, $pass) {
//	//"simple_password" ir tabulas nosaukums DB
//
//	$query = $this->db->get_where('simple_password', 
//				    array('user' => $user));
//	$items = $query->result();
//	if (sizeof($items) == 1) {
//	    $usrrow = $items[0];
//	    return ($pass == $usrrow->password);
//	} else {
//	    //lietotājs nav atrodams
//	    return false;
//	}
//    }
//
//    //DB glabājas parole, no kuras izrēķināts SHA-1
//    //salīdzina sha1(iesūtītā parole) <-> db paroles hašs
//    public function hashed_password($user, $pass) {
//	$query = $this->db->get_where('hashed_password', 
//				    array('user' => $user));
//	$items = $query->result();
//	if (sizeof($items) == 1) {
//	    $usrrow = $items[0];
//	    return (sha1($pass) == $usrrow->passhash);
//	} else {
//	    //lietotājs nav atrodams
//	    return false;
//	}
//    }
//
//    //DB glabājas parole, kurai pirms hašošanas beigās pielīmēts salt
//    //salīdzina sha1(iesūtītā parole + salt) <-> db paroles + salta hašs 
//    public function salted_password($user, $pass) {
//	$query = $this->db->get_where('salted_password', 
//			    array('user' => $user));
//	$items = $query->result();
//	if (sizeof($items) == 1) {
//	    $usrrow = $items[0];
//	    return (sha1($pass . $usrrow->salt) == $usrrow->passhash);
//	} else {
//	    //lietotājs nav atrodams
//	    return false;
//	}
//    }
    //DB glabājas sha1( sha1 (parole) + salt ) 
    //klients iesūta sha1(parole)
    //salīdzina sha1(iesūtītā parole + salt) <-> db glabātais hešs 
    public function clienthash_password($user, $pass) {
	$query = $this->db->get_where('user', array('name' => $user));
	$items = $query->result();
	if (sizeof($items) == 1) {
	    $usrrow = $items[0];

	    if ((sha1($pass . $usrrow->salt) == $usrrow->password)) {
		//gadījums, kad autentifikācija tiešām veiksmīga
		$return = array();
		$return["username"] = $usrrow->name;
		$return["id"] = $usrrow->id;
		$return["role"] = $usrrow->role;
		return $return;
	    } else {
		return false;
	    }
	} else {
	    //lietotājs nav atrodams
	    return false;
	}
    }

    /*     * *
     * Lietotāju reģistrācijas funkcijas
     */

    public function register_user($user, $pass, $email, $website) {
	//meklēsim DB, vai tur jau tāds lietotājs nav definēts
	$query = $this->db->get_where('user', array('name' => $user));
	$items = $query->result();
	if (sizeof($items) > 0) {
	    return "Lietotājs ar šādu vārdu jau reģistrēts!";
	}

	//izdomājam jaunu salt vērtību - ciparu virknīte
	$salt = md5(uniqid(rand(), TRUE));

	//parole tiks izmantota lietojumiem, kur lietotājs jau iesūta sha1(pass)
	$passhash = sha1(sha1($pass) . $salt);

	$data = array(
	    'name' => $user,
	    'password' => $passhash,
	    'salt' => $salt,
	    'email' => $email,
	    'website' => $website,
	    'validated' => 0
	);
	$this->db->insert('user', $data);

	return $this->send_validation_email($email, $user, $salt);
    }

    private function send_validation_email($mail, $user, $salt) {

	//todo: izveidot konstantes MAIL_USER, MAIL_PASSWORD, USER_NAME
	$config = Array(
	    'protocol' => 'smtp',
	    'smtp_host' => 'ssl://smtp.gmail.com',
	    'smtp_port' => 465,
	    'smtp_user' => MAIL_USER,
	    'smtp_pass' => MAIL_PASSWORD,
	);
	$this->load->library('email', $config);
	$this->email->set_newline("\r\n");

	$this->email->from(MAIL_USER, USER_NAME);
	$this->email->to($mail);

	$this->email->subject('Reģistrācija WebGal sistēmā');

	$mailbody = "Sveiki!
	Lai pabeigtu reģistrāciju, lūdzu, uzklikšķini uz šīs saites:
	" . site_url("users/confirm/" . $user . "/" . sha1($salt));
	$this->email->message($mailbody);

	if (!$this->email->send())
	    return "Kļūda izsūtot e-pastu!";
	else
	    return true;
    }

    //pārbaude, vai hipersaitē iekļautais salthash atbilst reālā salt-a hešam
    //ja jā, to saglabā DB.
    public function validate($user, $salthash) {
	$query = $this->db->get_where('user', array('name' => $user));
	$items = $query->result();
	if (sizeof($items) == 1) {
	    $usrrow = $items[0];
	    if (sha1($usrrow->salt) == $salthash) {
		//db esošais lietotājs atbilst atsūtītajam!
		$this->db->where('name', $user);
		$this->db->update('registered_users', array('validated' => 1));
		return "Apstiprināšana veiksmīga";
	    } else {
		return "Mēģinājums viltot ievaddatus!";
	    }
	} else {
	    return "Lietotājs ar šādu vārdu nav atrasts!";
	}
    }

}

?>