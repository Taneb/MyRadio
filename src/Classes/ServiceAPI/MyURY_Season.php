<?php

/*
 * Provides the Season class for MyURY
 * @package MyURY_Scheduler
 */

/*
 * The Season class is used to create, view and manupulate Seasons within the new MyURY Scheduler Format
 * @version 12082012
 * @author Lloyd Wallis <lpw@ury.org.uk>
 * @package MyURY_Scheduler
 * @uses \Database
 * @uses \MyURY_Show
 * 
 */

class MyURY_Season extends MyURY_Scheduler_Common {

  private static $seasons = array();
  private $season_id;
  private $show_id;
  private $term_id;
  private $submitted;
  private $owner;
  private $metadata;
  private $timeslots;
  private $requested_times;
  private $requested_weeks;

  public static function getInstance($season_id = null) {
    if (!is_numeric($season_id)) {
      throw new MyURYException('Invalid Season ID!', MyURYException::FATAL);
    }

    if (!isset(self::$seasons[$season_id])) {
      self::$seasons[$season_id] = new self($season_id);
    }

    return self::$seasons[$season_id];
  }

  private function __construct($season_id) {
    $this->season_id = $season_id;
    //Init Database
    self::initDB();

    //Get the basic info about the season
    $result = self::$db->fetch_one('SELECT show_id, termid, submitted, memberid,
      (SELECT array(SELECT metadata_key_id FROM schedule.season_metadata WHERE show_season_id=$1 AND effective_from <= NOW()
        ORDER BY effective_from, season_metadata_id)) AS metadata_types,
      (SELECT array(SELECT metadata_value FROM schedule.season_metadata WHERE show_season_id=$1 AND effective_from <= NOW()
        ORDER BY effective_from, season_metadata_id)) AS metadata,
      (SELECT array(SELECT requested_day FROM schedule.show_season_requested_time WHERE show_season_id=$1
        ORDER BY preference ASC)) AS requested_days,
      (SELECT array(SELECT start_time FROM schedule.show_season_requested_time WHERE show_season_id=$1
        ORDER BY preference ASC)) AS requested_start_times,
      (SELECT array(SELECT duration FROM schedule.show_season_requested_time WHERE show_season_id=$1
        ORDER BY preference ASC)) AS requested_durations,
      (SELECT array(SELECT show_season_timeslot_id FROM schedule.show_season_timeslot WHERE show_season_id=$1
        ORDER BY start_time ASC)) AS timeslots,
      (SELECT array(SELECT week FROM schedule.show_season_requested_week WHERE show_season_id=$1)) AS requested_weeks
      FROM schedule.show_season WHERE show_season_id=$1', array($season_id));
    if (empty($result)) {
      //Invalid Season
      throw new MyURYException('The MyURY_Season with instance ID #' . $season_id . ' does not exist.');
    }

    //Deal with the easy bits
    $this->owner = User::getInstance($result['memberid']);
    $this->show_id = (int) $result['show_id'];
    $this->submitted = strtotime($result['submitted']);
    $this->term_id = (int) $result['termid'];

    $metadata_types = self::$db->decodeArray($result['metadata_types']);
    $metadata = self::$db->decodeArray($result['metadata']);
    //Deal with the metadata
    for ($i = 0; $i < sizeof($metadata_types); $i++) {
      if (self::isMetadataMultiple($metadata_types[$i])) {
        $this->metadata[$metadata_types[$i]][] = $metadata[$i];
      } else {
        $this->metadata[$metadata_types[$i]] = $metadata[$i];
      }
    }

    //Requested Weeks
    $requested_weeks = self::$db->decodeArray($result['requested_weeks']);
    foreach ($requested_weeks as $requested_week) {
      $this->requested_weeks[] = intval($requested_week);
    }

    //Requested timeslots
    $requested_days = self::$db->decodeArray($result['requested_days']);
    $requested_start_times = self::$db->decodeArray($result['requested_start_times']);
    $requested_durations = self::$db->decodeArray($result['requested_durations']);

    for ($i = 0; $i < sizeof($requested_days); $i++) {
      $this->requested_times[] = array(
          'day' => (int) $requested_days[$i],
          'start_time' => self::$db->intervalToTime($requested_start_times[$i]),
          'duration' => self::$db->intervalToTime($requested_durations[$i])
      );
    }

    //And now initiate timeslots
    $timeslots = self::$db->decodeArray($result['timeslots']);
    foreach ($timeslots as $timeslot) {
      //$this->timeslots[] = MyURYTimeslot::getInstance($timeslot);
    }
  }

  /**
   * Creates a new MyURY Season Application and returns an object representing it
   * @param Array $params An array of Seasons properties compatible with the Models/Scheduler/seasonfrm Form,
   * with a few additional potential customisation options:
   * weeks: An Array of weeks, keyed wk1-10, representing the requested week<br>
   * day: An Array of one or more requested days, 0 being Monday, 6 being Sunday. Corresponds to (s|e)time<br>
   * stime: An Array of sizeof(day) times, represeting the time of day the show should start<br>
   * etime: An Array of sizeof(day) times, represeting the time of day the show should end<br>
   * description: A description of this Season of the Show, in addition to the Show description<br>
   * tags: A string of 0 or more space-seperated tags this Season relates to, in addition to the Show tags<br>
   * show_id: The ID of the Show to assign the application to
   * termid: The ID of the term being applied for. Defaults to the current Term
   * 
   * weeks, day, stime, etime, show_id are all required fields
   * 
   * As this is the initial creation, all tags are <i>approved</i> by the submitted so the show has some initial values
   * 
   * @throws MyURYException
   */
  public static function apply($params = array()) {
    //Validate input
    $required = array('show_id', 'weeks', 'day', 'stime', 'etime');
    foreach ($required as $field) {
      if (!isset($params[$field])) {
        throw new MyURYException('Parameter ' . $field . ' was not provided.', MyURYException::FATAL);
      }
    }

    self::initDB();

    /**
     * @todo Perform required checks:
     * Show ID is a valid Show of the Show Type
     * All keys of weeks between 1 and 10 are defined and boolean (set to false if not defined)
     * All values of day are between 0 and 6
     * All values of stime and etime are between 0 and 86399
     * Select an appropriate value for $term_id
     */
    $term_id = self::getActiveApplicationTerm();

    //Start a transaction
    self::$db->query('BEGIN');

    //Right, let's start by getting a Season ID created for this entry
    $season_create_result = self::$db->fetch_column('INSERT INTO schedule.show_season (show_id, termid, submitted, memberid)
      VALUES ($1, $2, $3, $4) RETURNING show_season_id', array($params['show_id'], $term_id, CoreUtils::getTimestamp(), User::getInstance()->getID()), true);
    
    $season_id = $season_create_result[0];

    //Now let's allocate store the requested weeks for a term
    for ($i = 1; $i <= 10; $i++) {
      if ($params['weeks']["wk$i"]) {
        self::$db->query('INSERT INTO schedule.show_season_requested_week (show_season_id, week) VALUES ($1, $2)', array($season_id, $i), true);
      }
    }

    //Now for requested times
    for ($i = 0; $i < sizeof($params['day']); $i++) {
      //Deal with the possibility of a show from 11pm to midnight etc.
      if ($params['stime'][$i] < $params['etime'][$i]) {
        $interval = CoreUtils::makeInterval($params['stime'][$i], $params['etime'][$i]);
      } else {
        $interval = CoreUtils::makeInterval($params['etime'][$i], $params['stime'][$i]);
      }

      //Enter the data
      self::$db->query('INSERT INTO schedule.show_season_requested_time 
        (requested_day, start_time, preference, duration, show_season_id) VALUES ($1, $2, $3, $4, $5)', array($params['day'][$i], $params['stime'][$i], $i, $interval, $season_id));
    }

    //If the description metadata is non-blank, then update that too
    if (!empty($params['description'])) {
      self::$db->query('INSERT INTO schedule.season_metadata
        (metadata_key_id, show_season_id, metadata_value, effective_from, memberid, approvedid) VALUES
        ($1, $2, $3, NOW(), $4, $4)', array(
          self::getMetadataKey('description'), $season_id, $params['description'], $_SESSION['memberid']
              ), true);
    }

    //Same with tags
    if (!empty($params['tags'])) {
      $tags = explode(' ', $params['tags']);
      foreach ($tags as $tag) {
        if (empty($tag))
          continue;
        self::$db->query('INSERT INTO schedule.season_metadata
          (metadata_key_id, show_season_id, metadata_value, effective_from, memberid, approvedid) VALUES
          ($1, $2, $3, NOW(), $4, $4)', array(
            self::getMetadataKey('tag'), $season_id, $tag, $_SESSION['memberid']
                ), true);
      }
    }

    //Actually commit the show to the database!
    self::$db->query('COMMIT');

    return new self($season_id);
  }

  public function getMeta($meta_string) {
    $key = self::getMetadataKey($meta_string);
    if (isset($this->meta[$key])) {
      return $this->meta[$key];
    } else {
      return $this->getShow()->getMeta($meta_string);
    }
  }

  public function getID() {
    return $this->season_id;
  }

  public function getShow() {
    return MyURY_Show::getInstance($this->show_id);
  }

  public function getSubmittedTime() {
    return CoreUtils::happyTime($this->submitted);
  }

  public function getWebpage() {
    return 'http://ury.org.uk/show/' . $this->getShow()->getID() . '/' . $this->getID();
  }

  public function getRequestedTimes() {
    $return = array();
    foreach ($this->requested_times as $time) {
      $return[] = self::formatTimeHuman($time);
    }
    return $return;
  }

  /**
   * Returns a 2D array:
   * time: Value as per getRequestedTimes()
   * conflict: True if one or more of requested weeks already have a booking that time
   * info: If True above, will have human-readable why-it-is-a-conflict details. It may also contain information about
   * "warnings" - conflicts on weeks this show isn't planned to be aired
   * @todo The Warnings part above
   * @todo Discuss efficiency of this algorithm
   */
  public function getRequestedTimesAvail() {
    $return = array();
    foreach ($this->requested_times as $time) {
      //Check for existence of shows in requested times
      $conflicts = self::getScheduleConflicts($this->term_id, $time);
      $warnings = array();
      foreach ($conflicts as $wk => $sid) {
        if (!in_array($wk, $conflicts)) {
          //This isn't actually a conflict because the week isn't requested by the user
          $warnings[$wk] = $sid;
          unset($conflicts[$wk]);
        }
      }
      print_r($warnings);
      //If there's a any conflicts, let's make the nice explanation
      if (!empty($conflicts)) {
        $names = '';
        $weeks = ' on weeks ';
        $week_num = -10;
        //Count the number of conflicts with season ids
        $sids = array();
        foreach ($conflicts as $k => $v) {
          /**
           * @todo - figure out why this loop includes 0 and 11 sometimes
           * @todo Work on weeks text output - duplicates/overlaps
           */
          if ($k > 10 || $k < 1) continue;
          $sids[$v]++;
          //Work out blocked weeks
          if ($k == ++$week_num) {
            //Continuation of previous sequence
            $week_num = $k;
            if ($k == 10) $weeks .= '-10';
          } else {
            //New sequence
            if ($week_num > 0) $weeks .= '-' . $week_num . ', ';
            $weeks .= $k;
            $week_num = $k;
          }
        }
        //Iterate over every conflicted show and log
        foreach ($sids as $k => $v) {
          //Get the show name and store it
          if ($names !== '') $names .= ', ';
          $names .= self::getInstance($k)->getMeta('title');
        }
        $return[] = array('time' => self::formatTimeHuman($time), 'conflict' => true, 'info' => 'Conflicts with ' . $names . $weeks);
      } else {
        //No conflicts
        $return[] = array('time' => self::formatTimeHuman($time), 'conflict' => false, 'info' => '');
      }
    }
    return $return;
  }

  public function getRequestedWeeks() {
    return $this->requested_weeks;
  }

  public function toDataSource() {
    return array_merge($this->getShow()->toDataSource(), array(
                'id' => $this->getID(),
                'name' => $this->getMeta('title'),
                'submitted' => $this->getSubmittedTime(),
                'requested_time' => $this->getRequestedTimes()[0],
                'description' => $this->getMeta('description'),
                'editlink' => array(
                    'display' => 'text',
                    'url' => CoreUtils::makeURL('Scheduler', 'allocate', array('show_season_id' => $this->getID())),
                    'value' => 'Edit/Allocate'
                ),
                'rejectlink' => array(
                    'display' => 'text',
                    'url' => CoreUtils::makeURL('Scheduler', 'reject', array('show_season_id' => $this->getID())),
                    'value' => 'Reject'
                )
            ));
  }

}