<?php
/**
 *
 * @todo Proper Documentation
 * @author Lloyd Wallis <lpw@ury.org.uk>
 * @version 22092012
 * @package MyRadio_Scheduler
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    //Submitted
    $season = MyRadio_Season::getInstance($_SESSION['myury_working_with_season']);

    unset($_SESSION['myury_working_with_season']);

    $data = $season->getAllocateForm()->readValues();

    $season->schedule($data);

    CoreUtils::backWithMessage('Season Allocated!')

} else {
    //Not Submitted
    $season = MyRadio_Season::getInstance($_REQUEST['show_season_id']);

    /**
     * @todo WHY IS THIS IN THE SESSION
     */
    $_SESSION['myury_working_with_season'] = $season->getID();

    $season
        ->getAllocateForm()
        ->render($season->toDataSource());
}
