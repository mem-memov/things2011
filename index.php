<?php
// This code requires PHP 5.3 because it uses late static binding.
// UTF-8 without BOM
class node {

 protected $id; // string
 protected $parents; // collection
 protected $children; // collection
 
 protected $creation_time;
 protected $last_update_time;
 
 protected $to_be_updated; // boolean
 
 private static $count; // integer
 private static $cache = array(); // array

 public function __construct ($id=null) {
  $this->id = static::compose_id($id);
  $this->parents = collection::construct();
  $this->children = collection::construct();
  $this->creation_time = date('Y-m-d H:i:s');
  $this->last_update_time = date('Y-m-d H:i:s');
  $this->to_be_updated = false;
 }
 public static function exists ($id) {
  if (is_string($id) && strlen($id) > 0) {
   $path = static::path($id);
   return file_exists($path);
  }else{
   exit('Invalid '.static::class_name().' ID: '.var_dump($id));
  }
 }
 public static function construct ($id=null) {
  
  $instance = null;
  
  // try to read from file
  if (!is_null($id) && static::exists($id)) {
   $instance = static::read ($id);
  }
  
  
  // create if allowed
  if (is_null($instance) && true) {
   $instance = static::create($id);
  }

  return $instance;
 }
 public function __destruct () {
  if ($this->to_be_updated) {
   $this->to_be_updated = false;
   static::update($this);
   $this->last_update_time = date('Y-m-d H:i:s');
  }
 }
 
 public function must_be_updated ($decision=true) {
  $this->to_be_updated = $decision;
  return $this;
 }
 
 public function id () {
  return $this->id;
 }
 public function parents () {
  return $this->parents;
 }
 public function children () {
  return $this->children;
 }
 
 public function creation_time () {
  return $this->creation_time;
 }
 public function last_update_time () {
  return $this->last_update_time;
 }
 
 public static function remove ($id) {
  $path = static::path($id);
  if (file_exists($path)) {
   unlink($path);
   rmdir(dirname($path));
  }
 }
 
 public static function count () {
  return self::$count;
 }
 
 protected static function create ($id=null) {
  $class = static::class_name();
  $instance = new $class ($id); // object properties are initialized by __construct()
  self::$count++;
  return $instance;
 }
 protected static function read ($id) {
  if (isset(self::$cache[$id])) { // get node from cache
   return self::$cache[$id];
  }
  
  $instance = null;
  if (static::exists($id)) {
   $instance = unserialize(base64_decode(file_get_contents(static::path($id))));
   if (!(is_object($instance) && is_a($instance, __CLASS__))) {
    $instance = null;
   }else{ // put node to cache 
    self::$cache[$id] = $instance;
   }
  }
  self::$count++;
  return $instance;
 }
 protected static function update (node $instance) {
  if (isset(self::$cache[$instance->id()])) { // remove from cache
   unset(self::$cache[$instance->id()]);
  }
  
  $path = static::path($instance->id());
  static::make_directory($path);
  $bytes = file_put_contents($path, base64_encode(serialize($instance)));
  if ($bytes === false) {
   exit('A '.__CLASS__.' object could not be updated.');
  }
 }
 protected static function delete (node $instance) {
  unlink(static::path($instance->id()));
 }
  
 protected static function compose_id ($inspiration=null) {
  if (!is_null($inspiration) && !static::exists($inspiration)) {
   $id = $inspiration;
  }else{
   do {
    $id = '_'.md5($_SERVER['SERVER_NAME'].time().rand(1, 1000000).var_dump($inspiration));
   } while(file_exists(dirname(static::path($id))));
  }
  return $id;
 }
 protected static function class_name () { // overload this function in child classes
  return __CLASS__;
 }
 protected static function class_directory () {
  return static::class_name();
 }
 protected static function class_file () {
  return static::class_name().'.txt';
 }
 protected static function path ($id) {
  $ds = static::directory_separator();
  $path = rtrim(dirname(__FILE__), $ds).$ds.static::class_directory().$ds.$id.$ds.static::class_file();
  return $path;
 }
 protected static function make_directory ($path) { // secure that the given directory exists and return transformed path
  $ds = static::directory_separator();
  $current_directory = rtrim(dirname(__FILE__), $ds);
  $short_path = str_replace($current_directory.$ds, '', $path);
  $path_array = explode($ds, $short_path);
  $file_name = array_pop($path_array);
  foreach ($path_array as $directory) {
   $current_directory .= $ds.$directory;
   if (!file_exists($current_directory) || !is_dir($current_directory)) {
    $ok = mkdir($current_directory, 0777);
    if (!$ok) {
     exit('Folder '.$current_directory.' could not be created.');
    }
   }
  }
 }
 protected static function directory_separator () {
  if (strpos(__FILE__, '\\')) {
   $directory_separator = '\\';
  }elseif(strpos(__FILE__, '/')) {
   $directory_separator = '/';
  }else{
   $directory_separator = '/';
  }
  return $directory_separator;
 }

}
class word extends node {

 protected $entities; // collection
 protected $view; // string
 
 public function __construct ($id=null) {
  parent::__construct($id);
  $this->entities = collection::construct();
  $this->view = '';
 }

 public function entities () {
  return $this->entities;
 }

 public function ponder (entity $entity) {
  
  // update ids
  $this->entities->add($entity->id());
  
  // update parent word list
  foreach ($entity->parents()->to_array() as $id) {
   $parent_entity = entity::construct($id);
   $this->parents()->add($parent_entity->name());
  }
  
  // update child word list
  foreach ($entity->children()->to_array() as $id) {
   $child_entity = entity::construct($id);
   $this->children()->add($child_entity->name());
  }
  
  return $this;
  
 }

 public function recollect () {
 
  $this->to_be_updated = true;
 
  $children = collection::construct();
  $parents = collection::construct();
  
  foreach ($this->entities->to_array() as $id) {
   if (entity::exists($id)) {
    
    $here = entity::construct($id);
    
    // check word
    if ($here->name() != $this->id) {
     $this->entities->remove($id); // entity has other name
     continue;
    }
    
    // collect children
    foreach ($here->children->to_array() as $there_id) {
     if (entity::exists($there_id)) {
      $there = entity::construct($there_id);
      if ($there->parents()->exists($here->id())) {
       $children->add($there->name());
      }else{
       $there->parents()->add($here->id()); // repare connection
      }
     }
    }

    // collect parents
    foreach ($here->parents->to_array() as $there_id) {
     if (entity::exists($there_id)) {
      $there = entity::construct($there_id);
      if ($there->children()->exists($here->id())) {
       $parents->add($there->name());
      }else{
       $there->children()->add($here->id()); // repare connection
      }
     }
    }

   }else{
    $this->entities->remove($id); // entity absent
   }
   
  }
  
  $this->children = $children;
  $this->parents = $parents;
 
 }
 
 public function set_view ($view) {
  $this->view = $view;
  return $this;
 }
 public function view () {
  return $this->view;
 }

 public static function dictionary () {
  $dictionary = array();
  
  $ds = static::directory_separator();
  $path = rtrim(dirname(__FILE__), $ds).$ds.static::class_directory().$ds;
  $paths = glob($path.'*', GLOB_ONLYDIR);
  
  foreach ($paths as $path) {
   $dictionary[] = iconv(static::file_system_encoding(), static::php_encoding(), basename($path));
  }
  
  return $dictionary;
 }
 
 protected static function class_name () { 
  return __CLASS__;
 }
 protected static function path ($id) {
  $ds = static::directory_separator();
  $id = iconv(static::php_encoding(), static::file_system_encoding(), $id);
  $path = rtrim(dirname(__FILE__), $ds).$ds.static::class_directory().$ds.$id.$ds.static::class_file();
  return $path;
 }
 protected static function php_encoding () {
  return 'UTF-8';
 }
 protected static function file_system_encoding () {
  return 'Windows-1251';
 }

 
}
class entity extends node {
 
 const USE_DEFAULT_VIEW = 'USE_DEFAULT_VIEW';
 
 protected $name; // string
 protected $view; // string
 protected $owner; // string
 
 
 public function __construct ($id=null) {
  parent::__construct($id);
  $this->name = '';
  $this->view = self::USE_DEFAULT_VIEW;
  $this->owner = '';
 }
 public function __destruct () {

  if ($this->to_be_updated) {
   if ($this->name != '') {
    word::construct($this->name)->must_be_updated()->ponder($this);
   }
  }
  
  parent::__destruct ();
 }

 
 public function set_name ($name) {
  $this->name = $name;
  $word = word::construct($name);
  return $this;
 }
 public function name () {
  return $this->name;
 }
 public function set_view ($view) {
  $this->view = $view;
  return $this;
 }
 public function view () {
  $view = $this->view;
  return $view;
 }
 public function owner () {
  return $this->owner;
 }

 public function display ($arguments=null) {
  
  // prevent displaying
  if (isset($_GET['display']) && in_array($_GET['display'], array('0', 'off', 'out'))) {
   return __FILE__.': '.__FUNCTION__;
  }
  
  // display
  $q = query::construct();
  $e =& $this;
  $view = $this->view();
  if ($view == self::USE_DEFAULT_VIEW) {
   $view = word::construct($this->name)->view();
  }
  ob_start();
  eval(' ?>' . $view . '<?php ');
  $html = ob_get_contents();
  ob_end_clean();
  //$html = iconv('UTF-8', 'Windows-1251', $html);
  
  return $html;
  
 }
 
 public static function compose_owner ($email, $encripted_password) {
  return $email.':'.$encripted_password;
 }
 public function change_possible ($owner) {
  if ($owner == $this->owner || $this->owner == '') {
   return true;
  }else{
   return false;
  }
 }
 public function take_ownership ($owner) {
  if ($this->change_possible($owner)) {
   $this->owner = $owner;
  }
  return $this;
 }
 public function drop_ownership ($owner) {
  if ($this->change_possible($owner)) {
   $this->owner = '';
  }
  return $this;
 }
 public function give_ownership ($old_owner, $new_owner) {
  if ($this->change_possible($old_owner)) {
   $this->owner = $new_owner;
  }
  return $this;
 }
 
 public static function add_child ($name, $parent, $view = entity::USE_DEFAULT_VIEW) {
  
  // create
  $child = entity::construct()->set_name($name)->set_view($view);
  $child_id = $child->id();
  // bind
  $parent->children()->add($child_id);
  $child->parents()->add($parent->id());
  // update
  $child->must_be_updated()->take_ownership($parent->owner())->__destruct();
  $child = entity::construct($child_id);
  $parent->must_be_updated();
  
  return $child;
 } 
 public static function add_parent ($name, $child, $view = entity::USE_DEFAULT_VIEW) {
  
  // create
  $parent = entity::construct()->set_name($name)->set_view($view);
  $parent_id = $parent->id();
  // bind
  $parent->children()->add($child->id());
  $child->parents()->add($parent_id);
  // update
  $parent->must_be_updated()->take_ownership($child->owner())->__destruct();
  $parent = entity::construct($parent_id);
  $child->must_be_updated();
  
  return $parent;
 } 
 public static function remove_child ($name, $parent) {
  
 }
 public static function remove_parent ($name, $child) {
  
 }
 public static function check_parent ($parent_name, $child) {
  if ($child->parents()->count() !== 1) return false;
  $parent_id = array_pop($child->parents()->to_array());
  if (!entity::exists($parent_id)) return false;
  $parent = entity::construct($parent_id);
  if ($parent->name() != $parent_name) return false;
  return $parent;
 }
 
 
 protected static function class_name () { // use static::class_name() to call this function
  return __CLASS__;
 }

}
class collection {

 private $collection;

 public static function construct () {
  $class = __CLASS__;
  $instance = new $class ();
  $instance->collection = array();
  return $instance;
 }
 
 public function add ($value) {
  if (!$this->exists($value)) {
   $this->collection[] = $value;
  }
 }
 public function remove ($value) {
  $key = $this->key($value);
  if ($key !== false) {
   unset($this->collection[$key]);
   $this->collection = array_values($this->collection); // fill the gap
  }
 }
 
 public function exists ($value) {
  $key = $this->key($value);
  if ($key !== false) {
   return true;
  }else{
   return false;
  }
 }
 public function count () {
  return count($this->collection);
 }
 
 public function up ($value) {
  $key = $this->key($value);
  if ($key === 0) {
   array_shift($this->collection);
   $this->collection[] = $value;
  }else{
   $this->collection[$key] = $this->collection[$key-1];
   $this->collection[$key-1] = $value;
  }
  return $this;
 }
 public function down ($value) {
  $key = $this->key($value);
  if ($key === (count($this->collection)-1)) {
   array_pop($this->collection);
   array_unshift($this->collection, $value);
  }else{
   $this->collection[$key] = $this->collection[$key+1];
   $this->collection[$key+1] = $value;
  }
  return $this;
 }
 
 public function to_array () {
  return $this->collection;
 }
 public function to_string ($separator=',') {
  return implode($separator, $this->collection);
 }
 
 private function key ($value) {
  return array_search($value, $this->collection);
 }
 
}
class query {

 protected $name; // string
 protected $selection; // collection

 protected static $script_start_time;
 
 protected function __construct ($name=null) {
  $this->name = '';   
  $this->selection = collection::construct();
  
  if (!is_null($name)) {
   if (is_object($name)) {
    if (is_a($name, 'query')) {
     $this->name = $name->name();
     $this->selection = $name->to_collection();
    }elseif (is_a($name, 'entity')) {
     $this->name = $name->name();
     $this->selection->add($name->id());
    }elseif (is_a($name, 'word')) {
     $this->name = $name->id();
     $this->selection = $name->entities();
    }
   }elseif (word::exists($name)) {
    $this->name = $name;   
    $this->selection = word::construct($name)->entities();
   }elseif (entity::exists($name)) {
    $anchor = entity::construct($name);
    $this->name = $anchor->name();
    $this->selection->add($anchor->id());
   }
  }
  
 }
 public static function construct ($name=null) {
  $class = __CLASS__;
  $instance = new $class ($name); 
  return $instance;
 }

 public function name () {
  return $this->name;
 }
 
 public function get ($name=null) {
  $instance = static::construct($name);
  return $instance;
 }
 
 public function with ($description) { // filter
 
  if ($this->name == '' || $this->selection->count() == 0) return $this; // skip in empty objects
 
  list($delimitors, $names) = static::delimitors_and_names($description);
  foreach ($this->selection->to_array() as $entity_id) {
   if (entity::exists($entity_id)) {
    $current_entities = array(entity::construct($entity_id));
    foreach ($names as $index => $name) {
     $results = array();
     $delimitor = $delimitors[$index];
     if ($delimitor == ':') { // leads to parents
      foreach ($current_entities as $current_entity) {
       $this->find_parents($name, $current_entity, $results);
       if (count($results) == 0) { // decide
        $this->selection->remove($entity_id);
       }
      }
     }elseif ($delimitor == '.') { // leads to children
      foreach ($current_entities as $current_entity) {
       $this->find_children($name, $current_entity, $results);
       if (count($results) == 0) { // decide
        $this->selection->remove($entity_id);
       }
      }
     }elseif ($delimitor == '=') { // leads to view
      foreach ($current_entities as $current_entity) {
       if (isset($name[0])) {
        $operation = $name[0];
        switch ($operation) {
         case '*':
          $operand = substr($name, 1);
          if (strpos($current_entity->view(), $operand) !== false) {
           $results[] = $current_entity;
          }else{
           $this->selection->remove($entity_id);
          }
          break;
         case '^':
          $operand = substr($name, 1);
          if (strpos($current_entity->view(), $operand) === 0) {
           $results[] = $current_entity;
          }else{
           $this->selection->remove($entity_id);
          }
          break;
         case '$':
          $operand = substr($name, 1);
          if (strpos(strrev($current_entity->view()), strrev($operand)) === 0) {
           $results[] = $current_entity;
          }else{
           $this->selection->remove($entity_id);
          }
          break;
         default:
          $operand = $name;
          if ($current_entity->view() == $operand) {
           $results[] = $current_entity;
          }else{
           $this->selection->remove($entity_id);
          }
          break;
        }
       }
      }
     }
     $current_entities = $results;
    }
   }
  }
  
  return $this;
 }
 public function without ($description) { // filter
 
  if ($this->name == '' || $this->selection->count() == 0) return $this; // skip in empty objects
 
  list($delimitors, $names) = static::delimitors_and_names($description);
  foreach ($this->selection->to_array() as $entity_id) {
   if (entity::exists($entity_id)) {
    $current_entities = array(entity::construct($entity_id));
    foreach ($names as $index => $name) {
     $results = array();
     $delimitor = $delimitors[$index];
     if ($delimitor == ':') { // leads to parents
      foreach ($current_entities as $current_entity) {
       $this->find_parents($name, $current_entity, $results);
       if (count($results) > 0) { // decide
        $this->selection->remove($entity_id);
       }
      }
     }elseif ($delimitor == '.') { // leads to children
      foreach ($current_entities as $current_entity) {
       $this->find_children($name, $current_entity, $results);
       if (count($results) > 0) { // decide
        $this->selection->remove($entity_id);
       }
      }
     }
     $current_entities = $results;
    }
   }
  }
  
  return $this;
 }
 public function find ($description) { // collect amongst children (.) or parents (:)
  
  $found_entities = collection::construct();
  
  if ($this->name == '' || $this->selection->count() == 0) return $this; // skip in empty objects
 
  list($delimitors, $names) = static::delimitors_and_names($description);
  foreach ($this->selection->to_array() as $entity_id) {
   if (entity::exists($entity_id)) {
    $current_entities = array(entity::construct($entity_id));
    $results = array();
    foreach ($names as $index => $name) {
     $results = array();
     $delimitor = $delimitors[$index];
     if ($delimitor == ':') { // leads to parents
      foreach ($current_entities as $current_entity) {
       $this->find_parents($name, $current_entity, $results);
      }
     }elseif ($delimitor == '.') { // leads to children
      foreach ($current_entities as $current_entity) {
       $this->find_children($name, $current_entity, $results);
      }
     }
     $current_entities = $results;
    }
    $this->name = $name;
    foreach ($results as $entity) {
     $found_entities->add($entity->id());
    }
   }
  }
  
  $this->selection = $found_entities;
  
  return $this;
 }
 
 public function parents () { 
  
  if ($this->name == '' || $this->selection->count() == 0) return $this; // skip in empty objects

  $parents = collection::construct();
  foreach ($this->selection->to_array() as $entity_id) {
   if (entity::exists($entity_id)) { // avoid creating new entities
    foreach ($entity->parents()->to_array() as $parent_id) {
     $parents->add($parent_id);
    }
   }
  }
  $this->selection = $parents;
  
  return $this;
  
 }
 public function children () {
 
  if ($this->name == '' || $this->selection->count() == 0) return $this; // skip in empty objects

  $children = collection::construct();
  foreach ($this->selection->to_array() as $entity_id) {
   if (entity::exists($entity_id)) { // avoid creating new entities
    $entity = entity::construct($entity_id);
    foreach ($entity->children()->to_array() as $child_id) {
     $children->add($child_id);
    }
   }
  }
  $this->selection = $children;
  
  return $this;
  
 }
 
 public function split () {
  $split_array = array();
  foreach ($this->selection->to_array() as $id) {
   if (entity::exists($id)) {
    $split_array[] = $this->get($id);
   }
  }
  return $split_array;
 }
 
 public function to_views ($arguments=null) {
  $views = array();
  foreach ($this->selection->to_array() as $id) {
   if (entity::exists($id)) {
    $views[] = entity::construct($id)->display($arguments);
   }
  }
  return $views;
 }
 public function to_names () {
  $names = array();
  foreach ($this->selection->to_array() as $id) {
   if (entity::exists($id)) {
    $names[] = entity::construct($id)->name();
   }
  }
  return $names;
 }
 public function to_links () {
  $links = array();
  foreach ($this->selection->to_array() as $id) {
   if (entity::exists($id)) {
    $links[] = '<a href="?entity_id='.$id.'">'.entity::construct($id)->name().'</a>';
   }
  }
  return $links;
 }
 public function to_ids () {
  return $this->selection->to_array();
 }
 public function to_entities () {
  $entities = array();
  foreach ($this->selection as $id) {
   if (entity::exists($id)) {
    $entities[] = entity::construct($id);
   }
  }
  return $entities;
 }
 public function to_collection () {
  return $this->selection;
 }
 
 public function to_view ($arguments=null, $separator='', $wrapper_top='', $wrapper_bottom='') {
  $view = '';
  $views = $this->to_views($arguments);
  if (!empty($views)) {
   $view .= $wrapper_top;
   $view .= implode($separator, $views);
   $view .= $wrapper_bottom;
  }
  return $view;
 }
 public function to_menu ($separator='', $wrapper_top='', $wrapper_bottom='') {
  $menu = '';
  $links = $this->to_links();
  if (!empty($links)) {
   $menu .= $wrapper_top;
   $menu .= implode($separator, $links);
   $menu .= $wrapper_bottom;
  }
  return $menu;
 }
 
 public static function notation_to_code ($notation, $variable = '$q') {
  $code = '';
  if ($notation != '') {
   $code = $notation;
   $code = str_replace('?[', '->find(\'', $code);
   $code = str_replace('+[', '->with(\'', $code);
   $code = str_replace('-[', '->without(\'', $code);
   $code = str_replace(']', '\')', $code);
   $arrow_position = strpos($code, '->');
   if ($arrow_position !== false) {
    $word = substr($code, 0, $arrow_position);
    $code = $variable.'->get(\''.substr_replace($code, $word.'\')', 0, strlen($word)).';';
   }else{
    $code = $variable.'->get(\''.$code.'\');';
   }
  }
  return $code;
 }
 
 public static function set_script_start_time () {
  self::$script_start_time = self::time();
 }
 public static function time () {
  $mtime = microtime();
  $mtime = explode(" ",$mtime);
  $mtime = $mtime[1] + $mtime[0];
  return $mtime;
 } 
 public static function time_elapsed () {
  return self::time() - self::$script_start_time;
 }
 
 protected function find_parents ($name, $entity, &$parents=array(), &$track=array()) {

  if ($this->time_elapsed() > 10) { // s
   return;
  }
  if (memory_get_usage() > 6*1048576) { // Mb
   return;
  }
 
  if ($entity->parents()->count() == 0) { // no parents
   return;
  }
  
  foreach ($entity->parents()->to_array() as $parent_id) {
   if (entity::exists($parent_id)) {

    if (in_array($parent_id, $track)) { // avoid repetition
     continue;
    }else{
     $track[] = $parent_id;
    }
    
    $parent = entity::construct($parent_id);
    $parent_name = $parent->name();
    
    if ($parent_name == $this->name) { // avoid overlapping
     continue;
    }

    if ($parent_name == $name) { // names match
     $parents[] = $parent;
    }
    
    $this->find_parents($name, $parent, $parents, $track); // recursive function
   }
  }
  
  return;
 }
 protected function find_children ($name, $entity, &$children=array(), &$track=array()) {

  if ($this->time_elapsed() > 10) { // s
   return;
  }
  if (memory_get_usage() > 6*1048576) { // Mb
   return;
  }
 
 
  if ($entity->children()->count() == 0) { // no children
   return;
  }
  
  foreach ($entity->children()->to_array() as $child_id) {
   if (entity::exists($child_id)) {

    if (in_array($child_id, $track)) { // avoid repetition
     continue;
    }else{
     $track[] = $child_id;
    }
    
    $child = entity::construct($child_id);
    $child_name = $child->name();
    
    if ($child_name == $this->name) { // avoid overlapping
     continue;
    }

    if ($child_name == $name) { // names match
     $children[] = $child;
    }
    
    $this->find_children($name, $child, $children, $track); // recursive function
   }
  }
  
  return;
 }

 protected static function delimitors_and_names ($description) {
  preg_match_all('/(\.|\:|\=)([^\.\:\=]+)/', $description, $matches);
  $delimitors = $matches[1];
  $names = $matches[2];
  return array($delimitors, $names);
 }
}
class panel {
 
 private $entity; // entity
 private $owner; // string
 private $search_query; // string
 
 public static function construct ($id=null) {
  $class = __CLASS__;
  $instance = new $class ();
  return $instance;
 }
 
 public function set_entity (entity $entity) {
  $this->entity = $entity;
  return $this;
 }
 public function set_owner ($owner) {
  $this->owner = $owner;
  return $this;
 }
 public function set_search_query ($search_query) {
  $this->search_query = $search_query;
  return $this;
 }
 
 
 public function change_name ($name) {
  $old_name = $this->entity->name();
  
  $this->entity->set_name($name);
  $this->update_entity();
  
  $word = word::construct($old_name);
  $word->recollect();
  $word->must_be_updated()->__destruct(); // update
  
  return $this;
 }
 public function add_child ($child) {
 
  $id = trim($child, ' ');
  if (entity::exists($id)) { // id given
   $entity = entity::construct($id);
  }else{ // name given
   $entity = entity::construct()->set_name($child);
  }
  
  $this->entity->children()->add($entity->id());
  $this->update_entity();
  
  $entity
   ->take_ownership($this->entity->owner())
   ->parents()->add($this->entity->id())
  ;
  $entity->display(); // run action entity
  $entity->must_be_updated()->__destruct(); // update
  
  $word = word::construct($this->entity->name());
  $word->recollect();
  $word->must_be_updated()->__destruct(); // update
  
  return $this;
  
 }
 public function add_parent ($parent) {
  
  $id = trim($parent, ' ');
  if (entity::exists($id)) { // id given
   $entity = entity::construct($id);
   $entity = entity::construct($parent);
  }else{ // name given
   $entity = entity::construct()->set_name($parent);
  }
  
  $this->entity->parents()->add($entity->id());
  $this->update_entity();
  
  $entity
   ->take_ownership($this->entity->owner())
   ->children()->add($this->entity->id())
  ;
  $entity->must_be_updated()->__destruct(); // update
  
  $word = word::construct($this->entity->name());
  $word->recollect();
  $word->must_be_updated()->__destruct(); // update
  
  return $this;
  
 }
 public function remove_child ($id) {

  $this->entity->children()->remove($id);
  $this->update_entity();

  if (entity::exists($id)) { 
   $entity = entity::construct($id);
   $entity->parents()->remove($this->entity->id());
   $entity->must_be_updated()->__destruct(); // update
  }

  $word = word::construct($this->entity->name());
  $word->recollect();
  $word->must_be_updated()->__destruct(); // update
  
  return $this;

 }
 public function remove_parent ($id) {

  $this->entity->parents()->remove($id); 
  $this->update_entity();

  if (entity::exists($id)) { 
   $entity = entity::construct($id);
   $entity->children()->remove($this->entity->id());
   $entity->must_be_updated()->__destruct(); // update
  }

  $word = word::construct($this->entity->name());
  $word->recollect();
  $word->must_be_updated()->__destruct(); // update
  
  return $this;
  
 }
 public function delete_child ($id) {
  $this->remove_child($id);
  entity::remove($id);
 }
 public function delete_parent ($id) {
  $this->remove_parent($id);
  entity::remove($id);
 }
 public function up_child ($id) {
  $this->entity->children()->up($id);
  return $this;
 }
 public function up_parent ($id) {
  $this->entity->parents()->up($id);
  return $this;
 }
 public function down_child ($id) {
  $this->entity->children()->down($id);
  return $this;
 }
 public function down_parent ($id) {
  $this->entity->parents()->down($id);
  return $this;
 }
 public function change_view ($view) { // TODO: remove risky function calls from $view
  $old_error = serialize(error_get_last());
  
  try {
   $old_error_handler = set_error_handler(array(__CLASS__, 'view_error_handler'));
   $display = $this->entity->display(); // try out
   restore_error_handler();
  } catch (Exception $e) {
   echo 'Caught exception: ',  $e->getMessage(), "\n";
   exit();
  }
  
  $new_error = serialize(error_get_last());
  if ($old_error == $new_error) {
   $this->entity->set_view($view);
   $this->update_entity();
  }
  return $this;
 }
 public function view_to_default ($view) {
  if ($view != entity::USE_DEFAULT_VIEW) {
   $word = word::construct($this->entity->name())
    ->must_be_updated()
    ->set_view($view)
   ;
   unset($word); // update
  }
 }
 public function view_from_default () {
  $word = word::construct($this->entity->name());
  $this->entity->set_view($word->view());
 }
 public function delete_view () {
  $this->entity->set_view('');
  return $this;
 }
 public function take_entity () {
  $this->entity->take_ownership($this->owner);
 }
 public function drop_entity () {
  $this->entity->drop_ownership($this->owner);
 }
 public function give_entity ($owner) {
  $this->entity->give_ownership($this->owner, $owner);
 }

 public function user_can_change () {
  return ($this->entity->owner() == '' || $this->entity->owner() == $this->owner);
 }
 
 public function add_to_query ($command, $word) {
  if (!empty($word) && word::exists($word)) {
   switch ($command) {
    case '':
     if (empty($this->search_query)) {
      $this->search_query = $word;
     }
     break;
    case 'find_below':
     if (!empty($this->search_query)) {
      $this->search_query .= '?[.'.$word.']';
     }
     break;
    case 'find_above':
     if (!empty($this->search_query)) {
      $this->search_query .= '?[:'.$word.']';
     }
     break;
    case 'with':
     if (!empty($this->search_query)) {
      $this->search_query .= '+[.'.$word.']';
     }
     break;
    case 'without':
     if (!empty($this->search_query)) {
      $this->search_query .= '-[.'.$word.']';
     }
     break;
    case 'in':
     if (!empty($this->search_query)) {
      $this->search_query .= '+[:'.$word.']';
     }
     break;
    case 'not_in':
     if (!empty($this->search_query)) {
      $this->search_query .= '-[:'.$word.']';
     }
     break;
    case 'that_is_with':
     if (!empty($this->search_query) && strrpos($this->search_query, ']') == strlen($this->search_query)-1) {
      $this->search_query = substr($this->search_query, 0, strlen($this->search_query)-1);
      $this->search_query .= '.'.$word.']';
     }
     break;
    case 'that_is_in':
     if (!empty($this->search_query) && strrpos($this->search_query, ']') == strlen($this->search_query)-1) {
      $this->search_query = substr($this->search_query, 0, strlen($this->search_query)-1);
      $this->search_query .= ':'.$word.']';
     }
     break;
   }
  }
  return $this;
 }

 
 public function html () {
  $user_can_change_class = '';
  if ($this->user_can_change()) {
   $user_can_change_class = ' user_can_change ';
  }
  
  $html = '
    <head>
     <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
     <title></title>
     <style>
      * { font-family: sans-serif; }
      body, table {padding:0; margin:0;}
      form,div,a { padding:0; margin: 0; }
      td { vertical-align: top; padding: 10px; }
      textarea { width: 100%; height: 200px; }
      ul.links li form { display: inline; }
      .options form { display: inline; }
      .layout.general { width: 100%; }
      .layout.general td.title { width: 150px; font-weight: bold; text-align: right; }
      form.button { display: inline; }
      a.node { text-decoration: none; }
      a.node:hover { text-decoration: underline; }
      .node.parent { color: green; }
      .node.child { color: navy; }
      .node.self { color: red; font-weight: bold; }
      .referrer { color: purple; }
      .user_can_change { background-color: #eeffee; }
     </style>
    </head>
    <body>
     <div class="panel '.$user_can_change_class.'">
      <table class="layout general">
       <tr>
        <td class="title">Id:</td>
        <td>
         '.$this->entity->id().'
        </td>
       </tr>
       <tr>
        <td class="title">Name:</td>
        <td>
         <form method="post">
          <input class="node self" type="text" name="name" value="'.$this->entity->name().'" />
          <input type="submit" value="Change" />
         </form>
        </td>
       </tr>
       <tr>
        <td class="title">Parents:</td>
        <td>
         '.$this->parent_list().'
         <form method="post">
          <input class="node parent" type="text" name="parent" value="" />
          <input type="submit" value="Add" />
         </form>
         '.$this->parent_options().'
        </td>
       </tr>
       <tr>
        <td class="title">Children:</td>
        <td>
         '.$this->child_list().'
         <form method="post">
          <input class="node child" type="text" name="child" value="" />
          <input type="submit" value="Add" />
         </form>
         '.$this->child_options().'
        </td>
       </tr>
       <tr>
        <td class="title">View:</td>
        <td>
         <form method="post">
          <textarea name="view">'.$this->entity->view().'</textarea>
          <input type="submit" name="view_change" value="Change" />
          <input type="submit" name="view_to_default" value="To default view" />
          <input type="submit" name="view_from_default" value="From default view" />
          <input type="submit" name="view_delete" value="Delete view" />
          <input type="submit" name="view_auto" value="Auto" />
         </form>
         <div>
         '.$this->entity->display().'
         </div>
        </td>
       </tr>
       <tr>
        <td class="title">Ownership:</td>
        <td>
         <div>
         '.$this->ownership_form().'
         </div>
        </td>
       </tr>
       <tr>
        <td class="title">Search:</td>
        <td>
         <form method="post">
          '.$this->search_command_list().'
          '.$this->dictionary_list().'
          <input type="submit" name="update_query" value="Update query" />
          <textarea name="search_query">'.$this->search_query.'</textarea>
          <div><pre>'.query::notation_to_code($this->search_query).'</pre></div>
          '.$this->search_list().'
         </form>
        </td>
       </tr>
       <tr>
        <td class="title">Panel:</td>
        <td>
         <form method="post" action="?entity_id='.$this->entity->id().'&panel=off">
          <input type="submit" value="Off" />
         </form>
        </td>
       </tr>
      </table>
     </div>
    </body>
  ';
  
  return $html;
 }

 public static function view_error_handler ($errno, $errstr, $errfile, $errline) {
  switch ($errno) {
   case E_USER_ERROR:
    echo "<b>View ERROR</b> [$errno] $errstr<br />\n";
    echo "  Fatal error on line $errline in file $errfile";
    echo ", PHP " . PHP_VERSION . " (" . PHP_OS . ")<br />\n";
    echo "Aborting...<br />\n";
    exit(1);
    break;

   case E_USER_WARNING:
    echo "<b>View WARNING</b> [$errno] $errstr<br />\n";
    break;

   case E_USER_NOTICE:
    echo "<b>View NOTICE</b> [$errno] $errstr<br />\n";
    break;

   default:
    echo "Unknown error type in view: [$errno] $errstr<br />\n";
    break;
  }
  /* Don't execute PHP internal error handler */
  return true;
 }
 
 private function child_list () {
  
  $list = "\n";
  
  if ($this->entity->children()->count() > 0) { 
   $list .= '<table>';
   foreach($this->entity->children()->to_array() as $id) {
    if (entity::exists($id)) {
     $entity = entity::construct($id);
     $referrer_mark = '';
     if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'entity_id='.$entity->id()) !== false) {
      $referrer_mark = '<sup class="referrer">*</sup>';
     }
     $title = substr(strip_tags(strtr($entity->display(), "\n\t", '  ')), 0, 20);
     $list .= '<tr>'."\n";
     $list .= '<td><a class="child node" href="?entity_id='.$entity->id().'&panel=on" title="'.$title.'">'.$entity->name().'</a> '.$referrer_mark.'</td>'."\n";
     $list .= '<td>';
     $list .= '<form class="button" method="post"><input type="hidden" name="remove_child" value="'.$entity->id().'" /><input type="submit" value="X" /></form>'."\n";
     $list .= '<form class="button" method="post"><input type="hidden" name="delete_child" value="'.$entity->id().'" /><input type="submit" value="#" /></form>'."\n";
     $list .= '<form class="button" method="post"><input type="hidden" name="up_child" value="'.$entity->id().'" /><input type="submit" value="<" /></form>'."\n";
     $list .= '<form class="button" method="post"><input type="hidden" name="down_child" value="'.$entity->id().'" /><input type="submit" value=">" /></form>'."\n";
     $list .= '</td>';
     $list .= '</tr>'."\n";
    }else{
     // remove entity
    }
   }
   $list .= '</table>';
  }
  
  return $list;
 }
 private function parent_list () {
  //$word = word::construct($this->entity->name());
  $list = "\n";
  if ($this->entity->parents()->count() > 0) { 
   $list .= '<table>';
   foreach($this->entity->parents()->to_array() as $id) {
    if (entity::exists($id)) {
     $entity = entity::construct($id);
     $referrer_mark = '';
     if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'entity_id='.$entity->id()) !== false) {
      $referrer_mark = '<sup class="referrer">*</sup>';
     }
     $title = substr(strip_tags(strtr($entity->display(), "\n\t", '  ')), 0, 20);
     $list .= '<tr>'."\n";
     $list .= '<td><a class="parent node" href="?entity_id='.$entity->id().'&panel=on" title="'.$title.'">'.$entity->name().'</a> '.$referrer_mark.'</td>'."\n";
     $list .= '<td>';
     $list .= '<form class="button" method="post"><input type="hidden" name="remove_parent" value="'.$entity->id().'" /><input type="submit" value="X" /></form>'."\n";
     $list .= '<form class="button" method="post"><input type="hidden" name="delete_parent" value="'.$entity->id().'" /><input type="submit" value="#" /></form>'."\n";
     $list .= '<form class="button" method="post"><input type="hidden" name="up_parent" value="'.$entity->id().'" /><input type="submit" value="<" /></form>'."\n";
     $list .= '<form class="button" method="post"><input type="hidden" name="down_parent" value="'.$entity->id().'" /><input type="submit" value=">" /></form>'."\n";
     $list .= '</td>';
     $list .= '</tr>'."\n";
    }else{
     // remove entity
    }
   }
   $list .= '</table>';
  }
  return $list;
 }

 private function child_options () {
  
  $list = "\n";

  if ($this->entity->name() != '' && word::exists($this->entity->name())) {
   $word = word::construct($this->entity->name());
   if ($word->children()->count() > 0) { 
    $list .= '<div class="options">';
    foreach($word->children()->to_array() as $id) {
     $list .= '<form method="post"><input type="hidden" name="child" value="'.$id.'" /><input type="submit" value="'.$id.'" /></form>'."\n";
    }
    $list .= '</div>';
   }
  }
  
  return $list;
 }
 private function parent_options () {
  
  $list = "\n";

  if ($this->entity->name() != '' && word::exists($this->entity->name())) {
   $word = word::construct($this->entity->name());
   if ($word->parents()->count() > 0) { 
    $list .= '<div class="options">';
    foreach($word->parents()->to_array() as $id) {
    $list .= '<form method="post"><input type="hidden" name="parent" value="'.$id.'" /><input type="submit" value="'.$id.'" /></form>'."\n";
    }
    $list .= '</div>';
   }
  }
  
  return $list;
 }

 private function ownership_form () {
  $html = '';
  
  if ($this->entity->owner() == '') {
   $html .= '<div>Entity belongs to the public.</div>';
   if ($this->owner != '') {
    $html .= '<form method="post">';
    $html .= '<input type="hidden" name="take_entity" value="" />';
    $html .= '<input type="submit" value="Take ownership" />';
    $html .= '</form>';
   }
  }else{
   if ($this->entity->owner() != $this->owner) {
    $html .= '<div>Entity belongs to '.$this->entity->owner().'</div>';
   }else{
    $html .= '<div>Entity belongs to you.</div>';
    $html .= '<form method="post">';
    $html .= '<input type="hidden" name="drop_entity" value="" />';
    $html .= '<input type="submit" value="Drop ownership" />';
    $html .= '</form>';
    $html .= '<form method="post">';
    $html .= '<span>New owner: </span>';    
    $html .= '<input type="text" name="new_owner" value="" />';
    $html .= '<input type="hidden" name="give_entity" value="" />';
    $html .= '<input type="submit" value="Give ownership" />';
    $html .= '</form>';
   }
  }
  
  if ($this->entity->owner() != $this->owner) {
  
  }
  
  if (empty($this->owner)) {
   $html .= '<form method="post">';
   $html .= '<span>E-mail: </span>';
   $html .= '<input type="text" name="email" value="" />';
   $html .= '<span>Password: </span>';
   $html .= '<input type="password" name="password" value="" />';
   $html .= '<input type="submit" value="Log in" />';
   $html .= '</form>';
  }else{
   $html .= '<div>You are '.$this->owner.'</div>';   
   $html .= '<form method="post">';
   $html .= '<input type="hidden" name="logout" value="" />';
   $html .= '<input type="submit" value="Log out" />';
   $html .= '</form>';
  }
  
  if ($this->entity->owner() == '' || $this->entity->owner() == $this->owner) {
   $html .= '<div>You can edit this entity.</div>'; 
  }else{
   $html .= '<div>You can not edit this entity.</div>'; 
  }

  
  return $html;
 }

 private function dictionary_list () {
  $dictionary_list = '<select name="dictionary">';
  $dictionary_list .= '<option value=""></option>';
  foreach (word::dictionary() as $word) {
   $dictionary_list .= '<option value="'.$word.'">'.$word.'</option>';
  }
  $dictionary_list .= '</select>';
  return $dictionary_list;
 }
 private function search_command_list () {
  return '
   <select name="search_command">
    <option value=""></option>
    <option value="find_above">find above</option>
    <option value="find_below">find below</option>
    <option value="with">with</option>
    <option value="without">without</option>
    <option value="in">in</option>
    <option value="not_in">not in</option>
    <option value="that_is_with">that is with</option>
    <option value="that_is_in">that is in</option>
   </select>
  ';
 }
 private function search_list () {
  $search_list = '';
  if (!empty($this->search_query)) {
   $q = query::construct();
   $q = eval('return '.query::notation_to_code($this->search_query).';');
   $search_list = $q->to_menu();
  }
  return $search_list;
 }
 
 
 private function update_entity () {
  $my_id = $this->entity->id();
  $this->entity->must_be_updated()->__destruct();
  $this->entity = entity::construct($my_id);
 }

 
}


query::set_script_start_time();

// session
session_start();

// panel on/off
if (isset($_GET['panel'])) {
 if (in_array($_GET['panel'], array('1','on','yes'))) {
  $_SESSION['panel_on'] = true;
 }elseif(in_array($_GET['panel'], array('0','off','no'))) {
  $_SESSION['panel_on'] = false;
 }
}
$panel_on = isset($_SESSION['panel_on']) ? $_SESSION['panel_on'] : false;

// owner set/unset
if ($panel_on) {

 if (isset($_POST['email'], $_POST['password'])) {
  $email = $_POST['email'];
  $password = $_POST['password'];
  $encripted_password = function_exists('encript_password') ? encript_password($password) : md5($password);
  $_SESSION['owner'] = entity::compose_owner($email, $encripted_password);
 }
 if (isset($_POST['logout'])) {
  unset($_SESSION['owner']);
 }
 
 
}
$owner = isset($_SESSION['owner']) ? $_SESSION['owner'] : '';


$default_entity_id = '_00000000000000000000000000000000';
if (!entity::exists($default_entity_id)) {
 entity::construct($default_entity_id)->set_name('default entity')->must_be_updated();
}
$entity_id = (isset($_GET['entity_id'])) ? $_GET['entity_id'] : $default_entity_id;


if (entity::exists($entity_id)) {
 $entity = entity::construct($entity_id);
 if ($panel_on) {
  
  $entity->must_be_updated();
 
  $panel = panel::construct()
   ->set_entity($entity)
   ->set_owner($owner)
  ;
  
  if ($panel->user_can_change()) {
   
   if (isset($_POST['name'])) {
    $panel->change_name($_POST['name']);
   }
   if (isset($_POST['parent'])) {
    $panel->add_parent($_POST['parent']);
   }
   if (isset($_POST['child'])) {
    $panel->add_child($_POST['child']);
   }
   if (isset($_POST['remove_parent'])) {
    $panel->remove_parent($_POST['remove_parent']);
   }
   if (isset($_POST['remove_child'])) {
    $panel->remove_child($_POST['remove_child']);
   }
   if (isset($_POST['delete_parent'])) {
    $panel->delete_parent($_POST['delete_parent']);
   }
   if (isset($_POST['delete_child'])) {
    $panel->delete_child($_POST['delete_child']);
   }
   if (isset($_POST['up_parent'])) {
    $panel->up_parent($_POST['up_parent']);
   }
   if (isset($_POST['up_child'])) {
    $panel->up_child($_POST['up_child']);
   }
   if (isset($_POST['down_parent'])) {
    $panel->down_parent($_POST['down_parent']);
   }
   if (isset($_POST['down_child'])) {
    $panel->down_child($_POST['down_child']);
   }
   if (isset($_POST['view'])) {
    if (isset($_POST['view_change'])) {
     $panel->change_view($_POST['view']);
    }   
    if (isset($_POST['view_to_default'])) {
     $panel->view_to_default($_POST['view']);
    }   
    if (isset($_POST['view_from_default'])) {
     $panel->view_from_default();
    }   
    if (isset($_POST['view_delete'])) {
     $panel->delete_view();
    }   
    if (isset($_POST['view_auto'])) {
     $panel->change_view(entity::USE_DEFAULT_VIEW);
    }   
   }

   if (isset($_POST['take_entity'])) {
    $panel->take_entity();
   }
   if (isset($_POST['drop_entity'])) {
    $panel->drop_entity();
   }
   if (isset($_POST['give_entity'], $_POST['new_owner']) && !empty($_POST['new_owner'])) {
    $panel->give_entity($_POST['new_owner']);
   }
   
   if (isset($_POST['search_query'])) {
    $panel->set_search_query($_POST['search_query']);
    if (isset($_POST['update_query'], $_POST['dictionary'], $_POST['search_command'])) {
     $panel->add_to_query($_POST['search_command'], $_POST['dictionary']);
    }
   }   
   
  }

 
  echo $panel->html();
  
 }else{
  echo $entity->display();
 }
}


