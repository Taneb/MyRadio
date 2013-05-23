<?php
/**
 * This file provides the NIPSWeb_ManagedPlaylist class for MyURY - Contains Jingles etc.
 * @package MyURY_NIPSWeb
 */

/**
 * The NIPSWeb_ManagedPlaylist class helps provide control and access to managed playlists
 * 
 * @version 15032013
 * @author Lloyd Wallis <lpw@ury.org.uk>
 * @package MyURY_NIPSWeb
 * @uses \Database
 */
class NIPSWeb_ManagedPlaylist extends ServiceAPI {
  /**
   * The Singleton store for ManagedPlaylist objects
   * @var NIPSWeb_ManagedPlaylist
   */
  private static $playlists = array();
  
  private $managed_playlist_id;
  
  protected $items;
  
  protected $name;
  
  protected $folder;
  
  private $item_ttl;
  
  /**
   * Initiates the ManagedPlaylist variables
   * @param int $playlistid The ID of the managed playlist to initialise
   * Note: Only links *non-expired* items
   */
  private function __construct($playlistid) {
    $this->managed_playlist_id = $playlistid;
    $result = self::$db->fetch_one('SELECT * FROM bapsplanner.managed_playlists WHERE managedplaylistid=$1 LIMIT 1',
            array($playlistid));
    if (empty($result)) {
      throw new MyURYException('The specified NIPSWeb Managed Playlist does not seem to exist');
      return;
    }
    
    $this->name = $result['name'];
    $this->folder = $result['folder'];
    $this->item_ttl = $result['item_ttl'];
    
    $items = self::$db->fetch_column('SELECT manageditemid FROM bapsplanner.managed_items WHERE managedplaylistid=$1
      AND (expirydate IS NULL OR expirydate > NOW())
      ORDER BY title', array($this->managed_playlist_id));
    $this->items = array();
    foreach ($items as $id) {
      /**
       * Pass this to the ManagedItem - it's called Dependency Injection and prevents loops and looks pretty
       * http://stackoverflow.com/questions/4903387/can-2-singleton-classes-reference-each-other
       * http://www.phparch.com/2010/03/static-methods-vs-singletons-choose-neither/
       */
      $this->items[] = NIPSWeb_ManagedItem::getInstance((int)$id, $this);
    }
  }
  
  /**
   * Returns the current instance of that ManagedItem object if there is one, or runs the constructor if there isn't
   * @param int $resid The ID of the ManagedItem to return an object for
   */
  public static function getInstance($resid = -1) {
    self::__wakeup();
    if (!is_numeric($resid)) {
      throw new MyURYException('Invalid ManagedPlaylistID!');
    }

    if (!isset(self::$playlists[$resid])) {
      self::$playlists[$resid] = new self($resid);
    }

    return self::$playlists[$resid];
  }
  
  /**
   * Return the NIPSWeb_ManagedItems that belong to this playlist
   * @return Array of ManagedItems
   */
  public function getItems() {
    return $this->items;
  }
  
  /**
   * Get the Title of the ManagedPlaylist
   * @return String
   */
  public function getTitle() {
    return $this->name;
  }
  
  /**
   * Get the unique manageditemid of the ManagedPlaylist
   * @return int
   */
  public function getID() {
    return $this->managed_playlist_id;
  }
  
  /**
   * Get the unique path of the ManagedPlaylist
   * @return String
   */
  public function getFolder() {
    return $this->folder;;
  }
  
  public static function getAllManagedPlaylists($editable_only = false) {
    if ($editable_only && !User::getInstance()->hasAuth(AUTH_EDITCENTRALRES)) return array();
    $result = self::$db->fetch_column('SELECT managedplaylistid FROM bapsplanner.managed_playlists ORDER BY name');
    $response = array();
    foreach ($result as $id) {
      $response[] = self::getInstance($id);
    }
    
    return $response;
  }
  
  /**
   * Returns an array of key information, useful for Twig rendering and JSON requests
   * @todo Expand the information this returns
   * @return Array
   */
  public function toDataSource() {
    return array(
        'title' => $this->getTitle(),
        'managedid' => $this->getID(),
        'folder' => $this->getFolder(),
    );
  }
}