<?php
/**
 * This file provides the NIPSWeb_ManagedItem class for MyURY - these are Jingles, Beds, Adverts and others of a similar
 * ilk
 * @package MyURY_NIPSWeb
 */

/**
 * The NIPSWeb_ManagedItem class helps provide control and access to Beds and Jingles and similar not-PPL resources
 * 
 * @version 13032013
 * @author Lloyd Wallis <lpw@ury.org.uk>
 * @package MyURY_NIPSWeb
 * @uses \Database
 */
class NIPSWeb_ManagedItem extends ServiceAPI {
  /**
   * The Singleton store for ManagedItem objects
   * @var MyURY_Track
   */
  private static $resources = array();
  
  private $managed_item_id;
  
  private $managed_playlist;
  
  private $folder;
  
  private $title;
  
  private $length;
  
  private $bpm;
  
  private $expirydate;
  
  private $member;
  
  /**
   * Initiates the ManagedItem variables
   * @param int $resid The ID of the managed resource to initialise
   * @param NIPSWeb_ManagedPlaylist $playlistref If the playlist is requesting this item, then pass the playlist object
   * @todo Length, BPM
   * @todo Seperate Managed Items and Managed User Items. The way they were implemented was a horrible hack, for which
   * I am to blame. I should go to hell for it, seriously - Lloyd
   */
  private function __construct($resid, $playlistref = null) {
    $this->managed_item_id = $resid;
    //*dies*
    $result = self::$db->fetch_one('SELECT manageditemid, title, length, bpm, NULL AS folder, memberid, expirydate,
        managedplaylistid
        FROM bapsplanner.managed_items WHERE manageditemid=$1
      UNION SELECT manageditemid, title, length, bpm, managedplaylistid AS folder, NULL AS memberid, NULL AS expirydate,
        NULL as managedplaylistid
        FROM bapsplanner.managed_user_items WHERE manageditemid=$1
      LIMIT 1',
            array($resid));
    if (empty($result)) {
      throw new MyURYException('The specified NIPSWeb Managed Item or Managed User Item does not seem to exist');
      return;
    }
    
    $this->managed_playlist = empty($result['managedplaylistid']) ? null : 
            (($playlistref instanceof NIPSWeb_ManagedPlaylist) ? $playlistref :
            NIPSWeb_ManagedPlaylist::getInstance($result['managedplaylistid']));
    $this->folder = $result['folder'];
    $this->title = $result['title'];
    $this->length = strtotime('1970-01-01 '.$result['length']);
    $this->bpm = (int)$result['bpm'];
    $this->expirydate = strtotime($result['expirydate']);
    $this->member = empty($result['memberid']) ? null : User::getInstance($result['memberid']);
  }
  
  /**
   * Returns the current instance of that ManagedItem object if there is one, or runs the constructor if there isn't
   * @param int $resid The ID of the ManagedItem to return an object for
   * @param NIPSWeb_ManagedPlaylist $playlistref If the playlist is requesting this item, then pass the playlist object
   * itself as a reference to prevent cyclic dependencies.
   */
  public static function getInstance($resid = -1, $playlistref = null) {
    self::__wakeup();
    if (!is_numeric($resid)) {
      throw new MyURYException('Invalid ManagedResourceID!');
    }

    if (!isset(self::$resources[$resid])) {
      self::$resources[$resid] = new self($resid, $playlistref);
    }

    return self::$resources[$resid];
  }
  
  /**
   * Get the Title of the ManagedItem
   * @return String
   */
  public function getTitle() {
    return $this->title;
  }
  
  /**
   * Get the unique manageditemid of the ManagedItem
   * @return int
   */
  public function getID() {
    return $this->managed_item_id;
  }
  
  /**
   * Get the length of the ManagedItem, in seconds
   * @todo Not Implemented as Length not stored in DB
   * @return int
   */
  public function getLength() {
    return $this->length;
  }
  
  /**
   * Get the path of the ManagedItem
   * @return string
   */
  public function getPath() {
    return Config::$music_central_db_path.'/'.($this->managed_playlist ? $this->managed_playlist->getFolder() : $this->folder).'/'.$this->getID().'.mp3';
  }
  
  /**
   * Returns an array of key information, useful for Twig rendering and JSON requests
   * @todo Expand the information this returns
   * @return Array
   */
  public function toDataSource() {
    return array(
        'type' => 'aux', //Legacy NIPSWeb Views
        'summary' => $this->getTitle(), //Again, freaking NIPSWeb
        'title' => $this->getTitle(),
        'managedid' => $this->getID(),
        'length' => CoreUtils::happyTime($this->getLength() > 0 ? $this->getLength() : 0, true, false),
        'trackid' => $this->getID(),
        'recordid' => 'ManagedDB', //Legacy NIPSWeb Views
        'auxid' => 'managed:' . $this->getID() //Legacy NIPSWeb Views
    );
  }

  public function cacheItem($tmp_path) {
    if (!isset($_SESSION['myury_nipsweb_file_cache_counter'])) $_SESSION['myury_nipsweb_file_cache_counter'] = 0;
    if (!is_dir(Config::$audio_upload_tmp_dir)) {
      mkdir(Config::$audio_upload_tmp_dir);
    }
    
    $filename = session_id() . '-' . ++$_SESSION['myury_nipsweb_file_cache_counter'] . '.mp3';
    
    move_uploaded_file($tmp_path, Config::$audio_upload_tmp_dir . '/' . $filename);
    
    return array(
        'fileid' => $filename,
    );
  }
}
