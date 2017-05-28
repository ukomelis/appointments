<?php
class User {
	private $_db,
			$_data,
			$_sessionName,
			$_cookieName,
			$_isLoggedIn;

	public function __construct($user = null) {
		$this->_db = DB::getInstance();
		$this->_sessionName = Config::get("session/session_name");
		$this->_cookieName = Config::get("remember/cookie_name");

		if(!$user){
			if(Session::exists($this->_sessionName)){
				$user = Session::get($this->_sessionName);

				if($this->find($user)){
					$this->_isLoggedIn = true;
				}else{
					//proccess logout
				}
			}
		}else{
			$this->find($user);
		}
		
	}

	public function create($fields = array()){
		if(!$this->_db->insert("users", $fields)){
			throw new Exception("There was a problem creating an account");
		}
	}

	public function find($user = null){
		if($user){
			//TODO change to support users with only numeric username
			$field = (is_numeric($user)) ? "id" : "username";
			$data = $this->_db->select("users", array($field, "=", $user));

			if($data->count()){
				$this->_data = $data->first();
				return true;
			}
		}
	}

	public function update($fields = array(), $id = null){
		if(!$id && $this->isLoggedIn()){
			$id = $this->data()->id;
		}

		if(!$this->_db->update("users", $id, $fields)){
			throw new Exception("There was a problem updating.");
		}
	}

	//returns true if user password and name matches and sets the session
	public function login($username = null, $password = null, $remember = false){
		if(!$username && !$password && $this->exists()){
			Session::put($this->_sessionName, $this->data()->id);
		}else{		
			$user = $this->find($username);

			if($user){
				if($this->data()->password === Hash::make($password, $this->data()->salt)){

					Session::put($this->_sessionName, $this->data()->id);

					//if the user has check the "remember me" button, generate a cookie for the user
					if($remember){
						$hash = Hash::unique();
						$hashCheck = $this->_db->select("user_sessions", array("user_id", "=", $this->data()->id));

						if(!$hashCheck->count()){
							$this->_db->insert("user_sessions", array(
								"user_id" => $this->data()->id,
								"hash" => $hash
								));
						}else{
							$hash = $hashCheck->first()->hash;
						}

						Cookie::put($this->_cookieName, $hash, Config::get("remember/cookie_expiry"));

					}

					return true;
				}
			}		
		}
		return false;
	}

	public function exists(){
		return (!empty($this->_data)) ? true : false;
	}

	public function hasPermission($key){
		$group = $this->_db->select("groups", array("id", "=", $this->data()->group));

		if($group->count()){
			$permissions = json_decode($group->first()->permissions, true);

			if($permissions[$key] == true){
				return true;
			}
		}
		return false;
	}

	public function logout(){
		$this->_db->delete("user_sessions", array("user_id", "=", $this->data()->id));

		Session::delete($this->_sessionName);
		Cookie::delete($this->_cookieName);

	}

	public function data(){
		return $this->_data;
	}

	public function isLoggedIn(){
		return $this->_isLoggedIn;
	}
}
?>