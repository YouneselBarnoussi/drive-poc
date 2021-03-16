<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Office365\SharePoint\ClientContext;
use Office365\Runtime\Auth\ClientCredential;
use Office365\SharePoint\ListItem;


class PocController extends Controller
{
	protected $sharepointClient;

	public function __construct( \JakubKlapka\LaravelSharepointUploadClient\Factories\ClientFactory $sharepointClient ) {
		$this->sharepointClient = $sharepointClient;
	}

	public function index() {
        $site_url = 'https://avans.sharepoint.com/sites/STU-Project-Everyware';
        $client_id = 'ff083f4b-b4dd-45ea-9105-f2410a5f5416';
        $client_secret = '0G75-G.3zFM~2fpcrRxQqwV12_Josm~~L7';

        $credentials = new ClientCredential($client_id, $client_secret);
        $client = (new ClientContext($site_url))->withCredentials($credentials);

        $web = $client->getWeb();
        $list = $web->getLists()->getByTitle("INCC"); //init List resource
        $items = $list->getItems();  //prepare a query to retrieve from the
        $client->load($items);  //save a query to retrieve list items from the server
        $client->executeQuery(); //submit query to SharePoint Online REST service
        /** @var ListItem $item */
        foreach($items as $item) {
            print "Task: {$item->getProperty('Title')}\r\n";
        }
    }

    public function hook() {
    }
}
