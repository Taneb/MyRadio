<?php
/**
 * Allows URY Trainers to create demo slots for new members to attend
 *
 * @author Andy Durant <aj@ury.org.uk>
 * @version 20140624
 * @package MyRadio_Scheduler
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    //Submitted
    MyRadio_Demo::getForm()->readValues();

    MyRadio_Demo::registerDemo($demoinfo['demo-datetime']);

    CoreUtils::backWithMessage('Demo Updated!');

} else {
    //Not Submitted

    MyRadio_Demo::getForm()
        ->setTemplate('Scheduler/createDemo.twig')
        ->render();
}
