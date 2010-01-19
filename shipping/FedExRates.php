<?php
/**
 * FedEx Rates
 * 
 * Uses FedEx Web Services to get live shipping rates based on product weight
 * 
 * INSTALLATION INSTRUCTIONS
 * Upload FedExRates.php to your Shopp install under:
 * ./wp-content/plugins/shopp/shipping/
 *
 * @author Jonathan Davis
 * @version 1.1
 * @copyright Ingenesis Limited, 22 January, 2009
 * @package shopp
 * @since 1.1 dev
 * @subpackage FedExRates
 * 
 * $Id$
 **/

class FedExRates extends ShippingFramework implements ShippingModule {
	var $test = false;
	var $wsdl_url = "";
	var $url = "https://gateway.fedex.com:443/web-services";
	var $test_url = "https://gatewaybeta.fedex.com:443/web-services";
	var $request = false;
	var $weight = 0;
	var $conversion = 1;
	var $Response = false;
	
	var $services = array(
		'FEDEX_GROUND' => 'FedEx Ground',
		'GROUND_HOME_DELIVERY' => 'FedEx Home Delivery',
		'FEDEX_EXPRESS_SAVER' => 'FedEx Express Saver',
		'FEDEX_2_DAY' => 'FedEx 2Day',
		'STANDARD_OVERNIGHT' => 'FedEx Standard Overnight',
		'PRIORITY_OVERNIGHT' => 'FedEx Priority Overnight',
		'FIRST_OVERNIGHT' => 'FedEx First Overnight',
		'INTERNATIONAL_ECONOMY' => 'FedEx International Economy',
		'INTERNATIONAL_FIRST' => 'FedEx International First',
		'INTERNATIONAL_PRIORITY' => 'FedEx International Priority',
		'EUROPE_FIRST_INTERNTIONAL_PRIORITY' => 'FedEx Europe First International Priority',
		'FEDEX_1_DAY_FREIGHT' => 'FedEx 1Day Freight',
		'FEDEX_2_DAY_FREIGHT' => 'FedEx 2Day Freight',
		'FEDEX_3_DAY_FREIGHT' => 'FedEx 3Day Freight',
		'INTERNATIONAL_ECONOMY_FREIGHT' => 'FedEx Economy Freight',
		'INTERNATIONAL_PRIORITY_FREIGHT' => 'FedEx Priority Freight'
		);
	var $deliverytimes = array(
		'ONE_DAY' => '1d',
		'TWO_DAYS' => '2d',
		'THREE_DAYS' => '3d',
		'FOUR_DAYS' => '4d',
		'FIVE_DAYS' => '5d',
		'SIX_DAYS' => '6d',
		'SEVEN_DAYS' => '7d',
		'EIGHT_DAYS' => '8d',
		'NINE_DAYS' => '9d',
		'TEN_DAYS' => '10d',
		'ELEVEN_DAYS' => '11d',
		'TWELVE_DAYS' => '12d',
		'THIRTEEN_DAYS' => '13d',
		'FOURTEEN_DAYS' => '14d',
		'FIFTEEN_DAYS' => '15d',
		'SIXTEEN_DAYS' => '16d',
		'SEVENTEEN_DAYS' => '17d',
		'EIGHTEEN_DAYS' => '18d',
		'NINETEEN_DAYS' => '19d',
		'TWENTY_DAYS' => '20d',
		'UNKNOWN' => '30d'
		);
	
	function __construct () {
		parent::__construct();

		$this->setup('account','meter','postcode','key','password');
		
		$units = array("imperial" => "LB","metric"=>"KG");
		$this->settings['units'] = $units[$this->base['units']];
		if ($this->units == 'oz') $this->conversion = 0.0625;
		if ($this->units == 'g') $this->conversion = 0.001;

		if (isset($this->rates[0])) $this->rate = $this->rates[0];
		
		add_action('shipping_service_settings',array(&$this,'settings'));
		add_action('shopp_verify_shipping_services',array(&$this,'verify'));

		$this->wsdl_url = add_query_arg('shopp_fedex','wsdl',get_bloginfo('siteurl'));
		$this->wsdl();
		
		if (defined('SHOPP_FEDEX_TESTMODE')) $this->test = SHOPP_FEDEX_TESTMODE;
		
	}
		
	function methods () {
		if (class_exists('SoapClient') || class_exists('SOAP_Client'))
			return array(__("FedEx Rates","Shopp"));
		elseif (class_exists('ShoppError'))
			new ShoppError("The SoapClient class is not enabled for PHP. The FedEx Rates add-on cannot be used without the SoapClient class.","fedexrates_nosoap",SHOPP_ALL_ERR);
	}
		
	function ui () { ?>
		function FedExRates (methodid,table,rates) {
			table.addClass('services').empty();
			
			if (!uniqueMethod(methodid,'<?php echo get_class($this); ?>')) return;
			
			var services = <?php echo json_encode($this->services); ?>;
			var settings = '';
			settings += '<tr><td>';
			settings += '<style type="text/css">#shipping-rates th.fedexrates { background: url(data:image/png;base64,<?php echo $this->logo(); ?>) no-repeat 20px 25px; }</style>';
			settings += '<input type="hidden" name="settings[shipping_rates]['+methodid+'][postcode-required]" value="true" />';

			settings += '<div class="multiple-select"><ul id="fedex-services">';
		
			settings += '<li><input type="checkbox" name="select-all" id="fedex-services-select-all" /><label for="fedex-services-select-all"><strong><?php _e('Select All','Shopp'); ?></strong></label>';
			var even = true;

			for (var service in services) {
				var checked = '';
				even = !even;
				for (var r in rates.services) 
					if (rates.services[r] == service) checked = ' checked="checked"';
				settings += '<li class="'+((even)?'even':'odd')+'"><input type="checkbox" name="settings[shipping_rates]['+methodid+'][services][]" value="'+service+'" id="fedex-service-'+service+'"'+checked+' /><label for="fedex-service-'+service+'">'+services[service]+'</label></li>';
			}

			settings += '</td>';
			
			settings += '<td>';
			settings += '<div><input type="text" name="settings[FedExRates][account]" id="fedexrates_account" value="<?php echo $this->settings['account']; ?>" size="11" /><br /><label for="fedexrates_account"><?php _e('Account Number','Shopp'); ?></label></div>';
			settings += '<div><input type="text" name="settings[FedExRates][meter]" id="fedexrates_meter" value="<?php echo $this->settings['meter']; ?>" size="11" /><br /><label for="fedexrates_meter"><?php _e('Meter Number','Shopp'); ?></label></div>';
			settings += '<div><input type="text" name="settings[FedExRates][postcode]" id="fedexrates_postcode" value="<?php echo $this->settings['postcode']; ?>" size="7" /><br /><label for="fedexrates_postcode"><?php _e('Your postal code','Shopp'); ?></label></div>';
				
			settings += '</td>';
			settings += '<td>';
			settings += '<div><input type="text" name="settings[FedExRates][key]" id="fedexrates_key" value="<?php echo $this->settings['key']; ?>" size="16" /><br /><label for="fedexrates_key"><?php _e('FedEx web services key','Shopp'); ?></label></div>';
			settings += '<div><input type="password" name="settings[FedExRates][password]" id="fedexrates_password" value="<?php echo $this->settings['password']; ?>" size="16" /><br /><label for="fedexrates_password"><?php _e('FedEx web services password','Shopp'); ?></label></div>';
			settings += '</td>';
			settings += '</tr>';

			$(settings).appendTo(table);

			$('#fedex-services-select-all').change(function () {
				if (this.checked) $('#fedex-services input').attr('checked',true);
				else $('#fedex-services input').attr('checked',false);
			});
				
			quickSelects();

		}

		methodHandlers.register('<?php echo get_class($this); ?>',FedExRates);

		<?php		
	}

	function init () {
		$this->weight = 0;
	}
	
	function calcitem ($id,$Item) {
 		$this->weight += ($Item->weight * $this->conversion) * $Item->quantity;
	}
	
	function calculate ($options,$Order) {
		if (empty($Order->Shipping->postcode)) {
			new ShoppError(__('A postal code for calculating shipping estimates and taxes is required before you can proceed to checkout.','Shopp','fedex_postcode_required',SHOPP_ERR));
			return $options;
		}

		$this->request = $this->build(session_id(), $this->rate['name'], 
			$Order->Shipping->postcode, $Order->Shipping->country);
		
		$this->Response = $this->send();
		if (!$this->Response) return false;
		if ($this->Response->HighestSeverity == 'FAILURE' || 
		 		$this->Response->HighestSeverity == 'ERROR') {
			new ShoppError($this->Response->Notifications->Message,'fedex_rate_error',SHOPP_ADDON_ERR);
			exit();
			return false;
		}

		$estimate = false;
		
		$RatedReply = &$this->Response->RateReplyDetails;
		if (!is_array($RatedReply)) return false;
		foreach ($RatedReply as $quote) {
			if (!in_array($quote->ServiceType,$this->rate['services'])) continue;
			
			$name = $this->services[$quote->ServiceType];
			if (is_array($quote->RatedShipmentDetails)) 
				$details = &$quote->RatedShipmentDetails[0];
			else $details = &$quote->RatedShipmentDetails;
			
			if (isset($quote->DeliveryTimestamp)) 
				$delivery = $this->timestamp_delivery($quote->DeliveryTimestamp);
			elseif(isset($quote->TransitTime))
				$delivery = $this->deliverytimes[$quote->TransitTime];
			else $delivery = '5d-7d';
			
			$amount = $details->ShipmentRateDetail->TotalNetCharge->Amount;

			$rate = array();
			$rate['name'] = $name;
			$rate['amount'] = $amount;
			$rate['delivery'] = $delivery;
			$options[$rate['name']] = new ShippingOption($rate);
		}
		
		return $options;
	}
	
	function timestamp_delivery ($datetime) {
		list($year,$month,$day,$hour,$min,$sec) = sscanf($datetime,"%4d-%2d-%2dT%2d:%2d:%2d");
		$days = ceil((mktime($hour,$min,$sec,$month,$day,$year) - mktime())/86400);
		return $days.'d';
	}
	
	function build ($session,$description,$postcode,$country) {
		
		$_ = array();

		$_['WebAuthenticationDetail'] = array(
			'UserCredential' => array(
				'Key' => $this->settings['key'], 
				'Password' => $this->settings['password']));

		$_['ClientDetail'] = array(
			'AccountNumber' => $this->settings['account'],
			'MeterNumber' => $this->settings['meter']);

		$_['TransactionDetail'] = array(
			'CustomerTransactionId' => empty($session)?mktime():$session);

		$_['Version'] = array(
			'ServiceId' => 'crs', 
			'Major' => '5', 
			'Intermediate' => '0', 
			'Minor' => '0');

		$_['ReturnTransitAndCommit'] = '1'; 

		$_['RequestedShipment'] = array();
		$_['RequestedShipment']['ShipTimestamp'] = date('c');
		
		// Valid values REGULAR_PICKUP, REQUEST_COURIER, ...
		$_['RequestedShipment']['DropoffType'] = 'REGULAR_PICKUP'; 
		
		$_['RequestedShipment']['Shipper'] = array(
			'Address' => array(
				'PostalCode' => $this->settings['postcode'],
				'CountryCode' => $this->base['country']));

		$_['RequestedShipment']['Recipient'] = array(
			'Address' => array(
				'PostalCode' => $postcode,
				'CountryCode' => $country));


		$_['RequestedShipment']['ShippingChargesPayment'] = array(
			'PaymentType' => 'SENDER',
			'Payor' => array('AccountNumber' => $this->settings['account'],
			'CountryCode' => 'US'));
			
		$_['RequestedShipment']['RateRequestTypes'] = 'ACCOUNT'; 
		// $_['RequestedShipment']['RateRequestTypes'] = 'LIST'; 
		$_['RequestedShipment']['PackageCount'] = '1';
		$_['RequestedShipment']['PackageDetail'] = 'INDIVIDUAL_PACKAGES';
		
		$_['RequestedShipment']['RequestedPackages'] = array(
				'SequenceNumber' => '1',
					'Weight' => array(
						'Units' => $this->settings['units'],
						'Value' => number_format(($this->weight < 0.1)?0.1:$this->weight,1,'.','')));
		
		return $_;
	} 
	
	function verify () {         
		if (!$this->activated()) return;
		$this->weight = 1;
		$this->request = $this->build('1','Authentication test','10012','US');
		$response = $this->send();
		if (isset($response->HighestSeverity)
			&& ($response->HighestSeverity == 'FAILURE'
			|| $response->HighestSeverity == 'ERROR')) 
		 	new ShoppError($response->Notifications->Message,'fedex_verify_auth',SHOPP_ADDON_ERR);
	}   
	
	function send () {
		try {
			if (class_exists('SoapClient')) {
				ini_set("soap.wsdl_cache_enabled", "1");
				$client = new SoapClient($this->wsdl_url);
				$response = $client->getRates($this->request);
			} elseif (class_exists('SOAP_Client')) {
				$WSDL = new SOAP_WSDL($this->wsdl_url);
				$client = $WSDL->getProxy();
				$returned = $client->getRates($this->request);
				$response = new StdClass();
				foreach ($returned as $key => $value) {
					if (empty($key)) continue;
					$response->{$key} = $value;
				}
				if (is_array($response->RateReplyDetails) && is_array($response->RateReplyDetails[0]))
					$response->RateReplyDetails = $this->fix_pear_soap_result_bug($response->RateReplyDetails);
				if(is_object($response->RateReplyDetails))
					$response->RateReplyDetails = array($response->RateReplyDetails);

			}
		} catch (Exception $e) {
			new ShoppError(__("FedEx could not be reached for realtime rates.","Shopp"),'fedex_connection',SHOPP_COMM_ERR);
			return false;
		}
		
		return $response;
	}
	
	// Workaround for a severe parse bug in PEAR-SOAP 0.12 beta				
	function fix_pear_soap_result_bug ($array) {
		$rates = array();
		foreach ($array as $value) {
			if (is_object($value)) $rates[] = $value;
			else $rates = array_merge($rates,$this->fix_pear_soap_result_bug($value));
		}
		return $rates;
	}
	
	function logo () {
		return 'iVBORw0KGgoAAAANSUhEUgAAAGIAAAAeCAMAAAGXnAsQAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAwBQTFRF0dHR6OjobTiVbjKJrq6unXi2ZiyOekieiV2qyMjI9/P5/f3+ZSuO/Pr9ysrK+Pf78/PzjmWtfEug7+nzwMDAoqKi+vj7m5ub2tra2NjY1tbWYiWOZCyQk5OTnnu5e0qffUygy7bZZy6Q6uLwtLS0sbGx3c/mkmmwuZ7MeEad9fX12Mrj8u72warS6+vr5ubmoKCg4+Pj5tzt49nr39/fdkObtJjJaS6Lh1qpbDWUhlqmkmiwcDuX9PD3xsbGoH25kmqwqorB49fp1MXg08LfrY/Dnp6etpvKl3G07ufyr5PGbDSUajOTekedm3a20b7dcz+ZaC+OvaPPuqLNt5zLs5fIsZXGrY/Eq4zCmHGzkWavaTGSZiyQf1CiZCuQe0ead0Wc/Pz8mZyXmpecdUaZtra2zs7Opqam+/n8mJiYZCyO/v7+ZSyO9/f3/f39urq6p6envr6+9O/3lGyy+vj8lpaWlZWVzMzMZS2PiFun+PX6+/v7+vr69/T5z8/Pt5zM3dHmg1WlpaWlqamppKSkuLi4/v3+vLy80cDew8PDmHK0+/r8mZmZaDCR+Pj4287lsrKy4tfqdkaa1sfhubm5u7u7mpicZC2PZS2OazST4ODgt7e35eXl+fn5jV6moojN8evzzc3NekedzbvblGuuuJ3MdEGackGXlW6yt7q27e3t5NrstZvK1cXgo362Zi6SnHa3Zy2NqKqmiWCoiWGsjGGrjGKt6ODu+fb5xLPd597t5t/wuLe4fE6haDKVZy+R+PX5s5rH19L3mnS1tp/S9PH59fL4eUObbjmYpoTA09PTy7jZzbzb/fz9ZCyP+fj7nJyckWay4NToe0uftqLVtaXf0cPf8u31n3y6n32+mJabkWiwvKTO5N/uzbjWz8HjlGyxgVCclm6zz7zc3Nzc8fHxjmSrbjyTglismHe3gFeq2Mnj2svjl5WaeUWeqaaqgVOkazeVhFOlhFWlo4G8p4a+2cvjup/NsZPI1cbhxcXFspTGs5bFl5eXZSyP////J5cw/AAAAQB0Uk5T////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////AFP3ByUAAAV5SURBVHjaYtgqpPAfCDanqwJJhssZIM7/lLz/YJ4fm/O/fztS2jv//gXySm48/vfvX8rfAiAPIIAYhIRAShTS00EK/4F1/fsL1vWvi60jGsjJ///3FFDm30eHhH9//xo2/QUIIIbLQO1M/6EAaNDfvwUQNlhG8R/bP+///8BW/P0rmvn3v/hfRqBMCVCMnZ393z+wHpDLQM75zyBXzPv/fzhIeN6/pcVyqampnakT/j9NZQEIIAYFIYinQMBBaFN6OswakOEwicp/SX///oVL/P+PLAFjg3VY/jPP+X8CxALpYPyb+f9vGVCi68a/nWv/7f+35f9GoER+/v9CkHkgo3j/CbGzL/qn/H8m1Ki/f+0gdvzLuvSv8d+/LxCj/v/NzAR6sXgbKDj+efz/z/PPtXgl0COqqXn/VVIBAohB6B8EMP1HA5X//k0CGYAIQaRwdDczMwvFoiMFqGNheXk5A4aOpfX17hP/h0b/+1cxHyR4EsjQEIToqEtLS5PiTEvjAwU8X1raL7AOEPD592+f7dFF//5F3gTyNOVk/kF0QFzFB4pDlr9/SyUgdnT//99W8Q8YxTCwHWheL0RHHtQxLCCtrTBXlQAZQB0Tocnl379n//9zT4foECksLOQEqoPY9gTIEoLq+O8DMT7m//8ZYEYvIqyaBICE7P8aIJkpABBADKZCYJBgjBZUXAFC796ng4HoXJSwgjm9AU1H0b9/0tMgVvzNJVpHMi4dCsAY/C2MXUczMAI5E9F1OPzHAqA6pmBIgO34Xl//4/91YFr891AZKKYMjMdFtVA7gHHOxwAkpIASEsD4N4H5o6LhHzgK2c78n3wOKgb3R6IskJD6PwdItsB9rvTv38YrGWuB5H/zf/945NiRdfT9B5GSpcDogLgq4HBX5N5//068Wfs6FsgDauD///8VRIdofn5+a83/6lKwVlmoPx4A6c//kIANUMAJoiMf6l8tsI4aqA51IP0BmKfYQUAemDqWAQVWo+rIBmno+Y+k4w/EZCBwBAYDsJSMgroKmBCnAo0u+wvTAtPBnfPvn4XRGmAiFAO5y17oH0qci//9WyYCZBkgdPz3h/rh4P8gNmjgwdNVdTqQMPnfCs5cDLeLi3+DXSOsCXTOiW9A1n2ghhNigsUX96SCgQrn7NTUNKBEa2rq7DqAAGPQ/IcKLNX+4wc6VuDYXX4LmnmgID0XlwZ4wQADTA0ErCiqBFuRkoxqRQHxVlj2k2dFE0ErHnH/JxKgWtGeTVADzAoPFCuK1HS9HMODJi9ACLV5ugSbBbsI/y+yItOKfzlQcOf/NwV4qMWZL/IHK/vJDhWZvt+vEWtANc+6AEnapZBqQ7IHzMvM1EePC0svkAkybOaxD16sB4uY7vq/Ox5qI9AKWHmBbkVL33+GTAhTXOK/XhOUaYge3RVsPGDa/AjYJf2WII7QvXgZsCjrsf//q9aZx2G3AhTdIihCfzPLkQOquPs/Nzf32f8vwXasZ2KyBAKmXmT7T0OC9iozD5IV+Xn/s0EgD1KHzVWBm19aWogaF+rg6un/DoV/uMBeaKG/ywbZF1Py0CJXzwBiQxmLwH+sVvxfAuZxtNVyAUFtFRBk1ApCArEDokKxAtmKqWgpihPhizIG7FYcYgab4FALNu2TBQeHhXVIAESNHDAuMhxxRTcoLiTEockIIuY2B5sV/+8eh8R4hY8ShNHb8f/bzAr0YMNWgDCKQoJIdBY83gtWAa1YlgUGUfxwv2rLIxnl8x0sphEYlwMR2B/BAVIv/fwtWr5gZIGy6kAacidAOLKqAgzdYhlAIMaLEqKei103rPD7qjsRkecPXItQ53AIA7ZvzoN18Caigb5qMFUNi5xsMD+vJhcA9lw4LrQUTaEAAAAASUVORK5CYII=';
	}
	
	function wsdl () {
		$lookup = (isset($_GET['shopp_fedex']))?$_GET['shopp_fedex']:'';
		if ($lookup != 'wsdl') return;
		$contents = '<?xml version="1.0" encoding="UTF-8"?>
<definitions xmlns="http://schemas.xmlsoap.org/wsdl/" xmlns:ns="http://fedex.com/ws/rate/v5" xmlns:s1="http://schemas.xmlsoap.org/wsdl/soap/" targetNamespace="http://fedex.com/ws/rate/v5" name="RateServiceDefinitions">
 <types>
   <xs:schema attributeFormDefault="qualified" elementFormDefault="qualified" targetNamespace="http://fedex.com/ws/rate/v5" xmlns:xs="http://www.w3.org/2001/XMLSchema">
     <xs:element name="RateRequest" type="ns:RateRequest"/>
     <xs:element name="RateReply" type="ns:RateReply"/>
     <xs:complexType name="RateRequest">
       <xs:annotation>
         <xs:documentation>Descriptive data sent to FedEx by a customer in order to rate a package/shipment.</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element minOccurs="1" name="WebAuthenticationDetail" type="ns:WebAuthenticationDetail">
           <xs:annotation>
             <xs:documentation>Descriptive data to be used in authentication of the sender\'s identity (and right to use FedEx web services).</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="1" name="ClientDetail" type="ns:ClientDetail">
           <xs:annotation>
             <xs:documentation>Descriptive data identifying the client submitting the transaction.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="TransactionDetail" type="ns:TransactionDetail">
           <xs:annotation>
             <xs:documentation>Descriptive data for this customer transaction. The TransactionDetail from the request is echoed back to the caller in the corresponding reply.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="Version" type="ns:VersionId">
           <xs:annotation>
             <xs:documentation>Identifies the version/level of a service operation expected by a caller (in each request) and performed by the callee (in each reply).</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="ReturnTransitAndCommit" type="xs:boolean" minOccurs="0">
           <xs:annotation>
             <xs:documentation>??</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="CarrierCodes" type="ns:CarrierCodeType" maxOccurs="unbounded" minOccurs="0">
           <xs:annotation>
             <xs:documentation>??</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="VariableOptions" type="ns:ServiceOptionType" maxOccurs="unbounded" minOccurs="0">
         </xs:element>
         <xs:element name="RequestedShipment" type="ns:RequestedShipment">
           <xs:annotation>
             <xs:documentation>??</xs:documentation>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:complexType name="RequestedShipment">
       <xs:annotation>
         <xs:documentation>The descriptive data for the shipment being tendered to FedEx.</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element name="ShipTimestamp" type="xs:dateTime" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Identifies the date and time the package is tendered to FedEx. Both the date and time portions of the string are expected to be used. The date should not be a past date or a date more than 10 days in the future. The time is the local time of the shipment based on the shipper\'s time zone. The date component must be in the format: YYYY-MM-DD (e.g. 2006-06-26). The time component must be in the format: HH:MM:SS using a 24 hour clock (e.g. 11:00 a.m. is 11:00:00, whereas 5:00 p.m. is 17:00:00). The date and time parts are separated by the letter T (e.g. 2006-06-26T17:00:00). There is also a UTC offset component indicating the number of hours/mainutes from UTC (e.g 2006-06-26T17:00:00-0400 is defined form June 26, 2006 5:00 pm Eastern Time).</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="DropoffType" type="ns:DropoffType" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Identifies the method by which the package is to be tendered to FedEx. This element does not dispatch a courier for package pickup. See DropoffType for list of valid enumerated values.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="ServiceType" type="ns:ServiceType" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Identifies the FedEx service to use in shipping the package. See ServiceType for list of valid enumerated values.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="PackagingType" type="ns:PackagingType" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Identifies the packaging used by the requestor for the package. See PackagingType for list of valid enumerated values.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="TotalWeight" type="ns:Weight" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Identifies the total weight of the shipment being conveyed to FedEx.This is only applicable to International shipments and should only be used on the first package of a mutiple piece shipment.This value contains 1 explicit decimal position</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="TotalInsuredValue" type="ns:Money" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Total insured amount.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="Shipper" type="ns:Party">
           <xs:annotation>
             <xs:documentation>Descriptive data identifying the party responsible for shipping the package. Shipper and Origin should have the same address.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="Recipient" type="ns:Party">
           <xs:annotation>
             <xs:documentation>Descriptive data identifying the party receiving the package.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="RecipientLocationNumber" type="xs:string" minOccurs="0">
           <xs:annotation>
             <xs:documentation>A unique identifier for a recipient location</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>10</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
         <xs:element name="ShippingChargesPayment" type="ns:Payment" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Descriptive data indicating the method and means of payment to FedEx for providing shipping services.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="SpecialServicesRequested" type="ns:ShipmentSpecialServicesRequested" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Descriptive data regarding special services requested by the shipper for this shipment. If the shipper is requesting a special service which requires additional data (e.g. COD), the special service type must be present in the specialServiceTypes collection, and the supporting detail must be provided in the appropriate sub-object. For example, to request COD, "COD" must be included in the SpecialServiceTypes collection and the CodDetail object must contain the required data.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="ExpressFreightDetail" type="ns:ExpressFreightDetail" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Details specific to an Express freight shipment.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="DeliveryInstructions" type="xs:string" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Used with Ground Home Delivery and Freight.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="VariableHandlingChargeDetail" type="ns:VariableHandlingChargeDetail" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Details about how to calculate variable handling charges at the shipment level.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="InternationalDetail" type="ns:InternationalDetail" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Information about this package that only applies to an international (export) shipment.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="PickupDetail" type="ns:PickupDetail" minOccurs="0">
           <xs:annotation>
             <xs:documentation>??</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="BlockInsightVisibility" type="xs:boolean" minOccurs="0">
           <xs:annotation>
             <xs:documentation>If true, only the shipper/payor will have visibility of this shipment.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="RateRequestTypes" type="ns:RateRequestType" minOccurs="0" maxOccurs="unbounded">
           <xs:annotation>
             <xs:documentation>Indicates the type of rates to be returned.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="PackageCount" type="xs:nonNegativeInteger" minOccurs="0">
           <xs:annotation>
             <xs:documentation>For a multiple piece shipment this is the total number of packages in the shipment.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="PackageDetail" type="ns:RequestedPackageDetailType">
           <xs:annotation>
             <xs:documentation>??</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="RequestedPackages" type="ns:RequestedPackage" minOccurs="0" maxOccurs="unbounded">
           <xs:annotation>
             <xs:documentation>Package level information about this package. Currently requests with multiple packages are not supported.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="RequestedPackageSummary" type="ns:RequestedPackageSummary" minOccurs="0">
           <xs:annotation>
             <xs:documentation>??</xs:documentation>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:complexType name="RequestedPackageSummary">
       <xs:annotation>
         <xs:documentation/>
       </xs:annotation>
       <xs:sequence>
         <xs:element name="Dimensions" type="ns:Dimensions" minOccurs="0">
           <xs:annotation>
             <xs:documentation/>
           </xs:annotation>
         </xs:element>
         <xs:element name="VariableHandlingChargeDetail" type="ns:VariableHandlingChargeDetail" minOccurs="0">
           <xs:annotation>
             <xs:documentation/>
           </xs:annotation>
         </xs:element>
         <xs:element name="SpecialServicesRequested" type="ns:PackageSpecialServicesRequested" minOccurs="0">
           <xs:annotation>
             <xs:documentation/>
           </xs:annotation>
         </xs:element>
         <xs:element name="CustomerReferences" type="ns:CustomerReference" minOccurs="0" maxOccurs="3">
           <xs:annotation>
             <xs:documentation>Reference information to be associated with this package.</xs:documentation>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:complexType name="PickupDetail">
       <xs:annotation>
         <xs:documentation/>
       </xs:annotation>
       <xs:sequence>
         <xs:element name="ReadyDateTime" type="xs:dateTime" minOccurs="0">
           <xs:annotation>
             <xs:documentation/>
           </xs:annotation>
         </xs:element>
         <xs:element name="LatestPickupDateTime" type="xs:dateTime" minOccurs="0">
           <xs:annotation>
             <xs:documentation/>
           </xs:annotation>
         </xs:element>
         <xs:element name="CourierInstructions" type="xs:string" minOccurs="0">
           <xs:annotation>
             <xs:documentation/>
           </xs:annotation>
         </xs:element>
         <xs:element name="RequestType" type="ns:PickupRequestType" minOccurs="0">
           <xs:annotation>
             <xs:documentation/>
           </xs:annotation>
         </xs:element>
         <xs:element name="RequestSource" type="ns:PickupRequestSourceType" minOccurs="0">
           <xs:annotation>
             <xs:documentation/>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:simpleType name="PickupRequestType">
       <xs:annotation>
         <xs:documentation>??</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="SAME_DAY"/>
         <xs:enumeration value="FUTURE_DAY"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:simpleType name="PickupRequestSourceType">
       <xs:annotation>
         <xs:documentation>??</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="AUTOMATION"/>
         <xs:enumeration value="CUSTOMER_SERVICE"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:simpleType name="RequestedPackageDetailType">
       <xs:annotation>
         <xs:documentation>??</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="INDIVIDUAL_PACKAGES"/>
         <xs:enumeration value="PACKAGE_SUMMARY"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:complexType name="ContactAndAddress">
       <xs:sequence>
         <xs:element name="Contact" type="ns:Contact" minOccurs="0"/>
         <xs:element name="Address" type="ns:Address" minOccurs="0"/>
       </xs:sequence>
     </xs:complexType>
     <xs:complexType name="ExpressFreightDetail">
       <xs:annotation>
         <xs:documentation>Details specific to an Express freight shipment.</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element name="PackingListEnclosed" type="xs:boolean" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Indicates whether or nor a packing list is enclosed.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="ShippersLoadAndCount" type="xs:positiveInteger" minOccurs="0">
           <xs:annotation>
             <xs:documentation>
               Total shipment pieces.
               ie. 3 boxes and 3 pallets of 100 pieces each = Shippers Load and Count of 303.
               Applicable to International Priority Freight and International Economy Freight.
               Values must be in the range of 1 - 99999
             </xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="BookingConfirmationNumber" type="xs:string" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Required for International Freight shipping. Values must be 8- 12 characters in length.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>12</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
         <xs:element name="ReferenceLabelRequested" type="xs:boolean" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Currently not supported.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="BeforeDeliveryContact" type="ns:ExpressFreightDetailContact" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Currently not supported.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="UndeliverableContact" type="ns:ExpressFreightDetailContact" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Currently not supported.</xs:documentation>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:complexType name="ExpressFreightDetailContact">
       <xs:annotation>
         <xs:documentation>Currently not supported. Delivery contact information for an Express freight shipment.</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element name="Name" type="xs:string">
           <xs:annotation>
             <xs:documentation>Contact name.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>TBD</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
         <xs:element name="Phone" type="xs:string">
           <xs:annotation>
             <xs:documentation>Contact phone number.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>TBD</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:complexType name="InternationalDetail">
       <xs:sequence>
         <xs:element name="Broker" type="ns:Party" minOccurs="0">
           <xs:annotation>
             <xs:documentation>
               Descriptive data identifying the Broker responsible for the shipmet.
               Required if BROKER_SELECT_OPTION is requested in Special Services.
             </xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="ClearanceBrokerage" type="ns:ClearanceBrokerageType" minOccurs="0">
           <xs:annotation>
             <xs:documentation>
               Interacts both with properties of the shipment and contractual relationship with
               the shipper.
             </xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="ImporterOfRecord" type="ns:Party" minOccurs="0">
           <xs:annotation>
             <xs:documentation>
               Applicable only for Commercial Invoice. If the consignee and importer are not the same, the Following importer fields are required.
               Importer/Contact/PersonName
               Importer/Contact/CompanyName
               Importer/Contact/PhoneNumber
               Importer/Address/StreetLine[0]
               Importer/Address/City
               Importer/Address/StateOrProvinceCode - if Importer Country Code is US or CA
               Importer/Address/PostalCode - if Importer Country Code is US or CA
               Importer/Address/CountryCode
             </xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="RecipientCustomsIdType" type="ns:RecipientCustomsIdType" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Type of Brazilian taxpayer identifier provided in Recipient/TaxPayerIdentification/Number. For shipments bound for Brazil this overrides the value in Recipient/TaxPayerIdentification/TinType</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="DutiesPayment" type="ns:Payment" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Indicates how payment of duties for the shipment will be made.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="DocumentContent" type="ns:InternationalDocumentContentType" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Indicates whether this shipment contains documents only or non-documents.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="CustomsValue" type="ns:Money" minOccurs="0">
           <xs:annotation>
             <xs:documentation>The total customs value for the shipment. This total will rrepresent th esum of the values of all commodities, and may include freight, miscellaneous, and insurance charges. Must contain 2 explicit decimal positions with a max length of 17 including the decimal. For Express International MPS, the Total Customs Value is in the master transaction and all child transactions</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="CommercialInvoice" type="ns:CommercialInvoice" minOccurs="0">
           <xs:annotation>
             <xs:documentation>CommercialInvoice element is required for electronic upload of CI data. It will serve to create/transmit an Electronic Commercial Invoice through FedEx’s System. Customers are responsible for printing their own Commercial Invoice. Commercial Invoice support consists of a maximum of 20 commodity line items.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="Commodities" type="ns:Commodity" minOccurs="0" maxOccurs="99">
           <xs:annotation>
             <xs:documentation>
               For international multiple piece shipments, commodity information must be passed in the Master and on each child transaction.
               If this shipment cotains more than four commodities line items, the four highest valued should be included in the first 4 occurances for this request.
             </xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="ExportDetail" type="ns:ExportDetail" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Country specific details of an International shipment.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="AdmissibilityPackageType" type="ns:AdmissibilityPackageType" minOccurs="0">
           <xs:annotation>
             <xs:documentation>
               Required for shipments inbound to Canada, and for shipments from Canada or Mexico into the United States or Puerto Rico.
             </xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="RegulatoryControls" type="ns:RegulatoryControlType" minOccurs="0" maxOccurs="unbounded">
           <xs:annotation>
             <xs:documentation>FOOD_OR_PERISHABLE is required by FDA/BTA; must be true for food/perishable items coming to US or PR from non-US/non-PR origin.</xs:documentation>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:simpleType name="ClearanceBrokerageType">
       <xs:annotation>
         <xs:documentation>Specifies the type of brokerage to be applied to a shipment.</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="BROKER_INCLUSIVE"/>
         <xs:enumeration value="BROKER_INCLUSIVE_NON_RESIDENT_IMPORTER"/>
         <xs:enumeration value="BROKER_SELECT"/>
         <xs:enumeration value="BROKER_SELECT_NON_RESIDENT_IMPORTER"/>
         <xs:enumeration value="BROKER_UNASSIGNED"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:simpleType name="RegulatoryControlType">
       <xs:annotation>
         <xs:documentation>FOOD_OR_PERISHABLE is required by FDA/BTA; must be true for food/perishable items coming to US or PR from non-US/non-PR origin</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="EU_CIRCULATION"/>
         <xs:enumeration value="FOOD_OR_PERISHABLE"/>
         <xs:enumeration value="NAFTA"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:simpleType name="RecipientCustomsIdType">
       <xs:annotation>
         <xs:documentation>Type of Brazilian taxpayer identifier provided in Recipient/TaxPayerIdentification/Number. For shipments bound for Brazil this overrides the value in Recipient/TaxPayerIdentification/TinType</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="COMPANY"/>
         <xs:enumeration value="INDIVIDUAL"/>
         <xs:enumeration value="PASSPORT"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:simpleType name="AdmissibilityPackageType">
       <xs:restriction base="xs:string">
         <xs:enumeration value="BAG"/>
         <xs:enumeration value="BBL"/>
         <xs:enumeration value="BDL"/>
         <xs:enumeration value="BOX"/>
         <xs:enumeration value="BSK"/>
         <xs:enumeration value="BXT"/>
         <xs:enumeration value="CAG"/>
         <xs:enumeration value="CAS"/>
         <xs:enumeration value="CHS"/>
         <xs:enumeration value="CNT"/>
         <xs:enumeration value="CRT"/>
         <xs:enumeration value="CTN"/>
         <xs:enumeration value="CYL"/>
         <xs:enumeration value="DRM"/>
         <xs:enumeration value="ENV"/>
         <xs:enumeration value="PAL"/>
         <xs:enumeration value="PCL"/>
         <xs:enumeration value="PCS"/>
         <xs:enumeration value="PKG"/>
         <xs:enumeration value="PLT"/>
         <xs:enumeration value="REL"/>
         <xs:enumeration value="ROL"/>
         <xs:enumeration value="SAK"/>
         <xs:enumeration value="SHW"/>
         <xs:enumeration value="SKD"/>
         <xs:enumeration value="TBE"/>
         <xs:enumeration value="TBN"/>
         <xs:enumeration value="TNK"/>
         <xs:enumeration value="UNT"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:complexType name="ExportDetail">
       <xs:annotation>
         <xs:documentation>Country specific details of an International shipment.</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element name="B13AFilingOption" type="ns:B13AFilingOptionType" minOccurs="0">
           <xs:annotation>
             <xs:documentation>
               Specifies which filing option is being exercised by the customer.
               Required for non-document shipments originating in Canada destined for any country other than Canada, the United States, Puerto Rico or the U.S. Virgin Islands.
             </xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="ExportComplianceStatement" type="xs:string" minOccurs="0">
           <xs:annotation>
             <xs:documentation>
               Required only if B13AFilingOption is one of the following:
               FILED_ELECTRONICALLY
               MANUALLY_ATTACHED
               SUMMARY_REPORTING
               If B13AFilingOption = NOT_REQUIRED, this field should contain a valid B13A Exception Number.
             </xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>50</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
         <xs:element name="PermitNumber" type="xs:string" minOccurs="0">
           <xs:annotation>
             <xs:documentation>This field is applicable only to Canada export non-document shipments of any value to any destination. No special characters allowed. </xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>10</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
         <xs:element name="DestinationControlDetail" type="ns:DestinationControlDetail" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Department of Commerce/Department of State information about this shipment.</xs:documentation>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:complexType name="DestinationControlDetail">
       <xs:annotation>
         <xs:documentation>Department of Commerce/Department of State information about this shipment.</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element name="StatementTypes" type="ns:DestinationControlStatementType" minOccurs="0" maxOccurs="unbounded">
           <xs:annotation>
             <xs:documentation>List of applicable Statment types.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="DestinationCountries" type="xs:string" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Countries this shipment is destined for.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="EndUser" type="xs:string" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Department of State End User.</xs:documentation>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:simpleType name="DestinationControlStatementType">
       <xs:annotation>
         <xs:documentation>Used to indicate whether the Destination Control Statement is of type Department of Commerce, Department of State or both.</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="DEPARTMENT_OF_COMMERCE"/>
         <xs:enumeration value="DEPARTMENT_OF_STATE"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:simpleType name="B13AFilingOptionType">
       <xs:annotation>
         <xs:documentation>
           Specifies which filing option is being exercised by the customer.
           Required for non-document shipments originating in Canada destined for any country other than Canada, the United States, Puerto Rico or the U.S. Virgin Islands.
         </xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="FILED_ELECTRONICALLY"/>
         <xs:enumeration value="MANUALLY_ATTACHED"/>
         <xs:enumeration value="NOT_REQUIRED"/>
         <xs:enumeration value="SUMMARY_REPORTING"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:complexType name="CommercialInvoice">
       <xs:annotation>
         <xs:documentation>CommercialInvoice element is required for electronic upload of CI data. It will serve to create/transmit an Electronic Commercial Invoice through FedEx System. Customers are responsible for printing their own Commercial Invoice. Commercial Invoice support consists of a maximum of 99 commodity line items.</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element name="Comments" type="xs:string" minOccurs="0" maxOccurs="99">
           <xs:annotation>
             <xs:documentation>Commercial Invoice comments to be uploaded to customs.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>444</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
         <xs:element name="FreightCharge" type="ns:Money" minOccurs="0">
           <xs:annotation>
             <xs:documentation>
               Format: Two explicit decimal positions max length 19 including decimal.
               Required if Terms Of Sale is CFR or  CIF.
               This charge should be added to the total customs value amount.
             </xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="InsuranceCharge" type="ns:Money" minOccurs="0">
           <xs:annotation>
             <xs:documentation>
               Format: Two explicit decimal positions max length 19 including decimal.
               Required if Terms Of Sale is CIF.
               This charge should be added to the total customs value amount.
             </xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="TaxesOrMiscellaneousCharge" type="ns:Money" minOccurs="0">
           <xs:annotation>
             <xs:documentation>
               Format: Two explicit decimal positions max length 19 including decimal.
               This charge should be added to the total customs value amount.
             </xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="Purpose" type="ns:PurposeOfShipmentType" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Test for the Commercial Invoice. Note that Sold is not a valid Purpose for a Proforma Invoice.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="PurposeOfShipmentDescription" type="xs:string" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Descriptive text for the purpose of the shipment.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="CustomerInvoiceNumber" type="xs:string" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Customer assigned invoice number.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>15</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
         <xs:element name="TermsOfSale" type="ns:TermsOfSaleType" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Defines the terms of the sale.</xs:documentation>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:simpleType name="TermsOfSaleType">
       <xs:annotation>
         <xs:documentation>
           Required for dutiable international express or ground shipment. This field is not applicable to an international PIB (document) or a non-document which does not require a commercial invoice express shipment.
           CFR_OR_CPT (Cost and Freight/Carriage Paid TO)
           CIF_OR_CIP (Cost Insurance and Freight/Carraige Insurance Paid)
           DDP (Delivered Duty Paid)
           DDU (Delivered Duty Unpaid)
           EXW (Ex Works)
           FOB_OR_FCA (Free On Board/Free Carrier)
         </xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="CFR_OR_CPT"/>
         <xs:enumeration value="CIF_OR_CIP"/>
         <xs:enumeration value="DDP"/>
         <xs:enumeration value="DDU"/>
         <xs:enumeration value="EXW"/>
         <xs:enumeration value="FOB_OR_FCA"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:simpleType name="PurposeOfShipmentType">
       <xs:annotation>
         <xs:documentation>Test for the Commercial Invoice. Note that Sold is not a valid Purpose for a Proforma Invoice.</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="GIFT"/>
         <xs:enumeration value="NOT_SOLD"/>
         <xs:enumeration value="PERSONAL_EFFECTS"/>
         <xs:enumeration value="REPAIR_AND_RETURN"/>
         <xs:enumeration value="SAMPLE"/>
         <xs:enumeration value="SOLD"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:simpleType name="InternationalDocumentContentType">
       <xs:annotation>
         <xs:documentation>The type of International shipment.</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="DOCUMENTS_ONLY"/>
         <xs:enumeration value="NON_DOCUMENTS"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:complexType name="Commodity">
       <xs:annotation>
         <xs:documentation>
           For international multiple piece shipments, commodity information must be passed in the Master and on each child transaction.
           If this shipment cotains more than four commodities line items, the four highest valued should be included in the first 4 occurances for this request.
         </xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element name="Name" type="xs:string" minOccurs="0">
           <xs:annotation>
             <xs:documentation>total number of pieces of this commodity</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="NumberOfPieces" type="xs:nonNegativeInteger" minOccurs="0">
           <xs:annotation>
             <xs:documentation>total number of pieces of this commodity</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="Description" type="xs:string" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Complete and accurate description of this commodity.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>450</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
         <xs:element name="CountryOfManufacture" type="xs:string" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Country code where commodity contents were produced or manufactured in their final form.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>2</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
         <xs:element name="HarmonizedCode" type="xs:string" minOccurs="0">
           <xs:annotation>
             <xs:documentation>
               Unique alpha/numeric representing commodity item.
               At least one occurrence is required for US Export shipments if the Customs Value is greater than $2500 or if a valid US Export license is required.
             </xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>14</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
         <xs:element name="Weight" type="ns:Weight" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Total weight of this commodity. 1 explicit decimal position. Max length 11 including decimal.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="Quantity" type="xs:nonNegativeInteger" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Number of units of a commodity in total number of pieces for this line item. Max length is 9</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="QuantityUnits" type="xs:string" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Unit of measure used to express the quantity of this commodity line item.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>3</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
         <xs:element name="UnitPrice" type="ns:Money" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Value of each unit in Quantity. Six explicit decimal positions, Max length 18 including decimal.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="CustomsValue" type="ns:Money" minOccurs="0">
           <xs:annotation>
             <xs:documentation>
               Total customs value for this line item.
               It should equal the commodity unit quantity times commodity unit value.
               Six explicit decimal positions, max length 18 including decimal.
             </xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="ExportLicenseNumber" type="xs:string" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Applicable to US export shipping only.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>12</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
         <xs:element name="ExportLicenseExpirationDate" type="xs:dateTime" minOccurs="0">
           <xs:annotation>
             <xs:documentation>
               Date of expiration. Must be at least 1 day into future.
               The date that the Commerce Export License expires. Export License commodities may not be exported from the U.S. on an expired license.
               Applicable to US Export shipping only.
               Required only if commodity is shipped on commerce export license, and Export License Number is supplied.
             </xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="CIMarksAndNumbers" type="xs:string" minOccurs="0">
           <xs:annotation>
             <xs:documentation>
               An identifying mark or number used on the packaging of a shipment to help customers identify a particular shipment.
             </xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>15</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
         <xs:element name="NaftaDetail" type="ns:NaftaCommodityDetail" minOccurs="0">
           <xs:annotation>
             <xs:documentation>
               All data required for this commodity in NAFTA Certificate of Origin.
             </xs:documentation>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:complexType name="NaftaCommodityDetail">
       <xs:annotation>
         <xs:documentation/>
       </xs:annotation>
       <xs:sequence>
         <xs:element name="PreferenceCriterion" type="ns:NaftaPreferenceCriterionCode" minOccurs="0">
           <xs:annotation>
             <xs:documentation>
               Defined by NAFTA regulations.
             </xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="ProducerDetermination" type="ns:NaftaProducerDeterminationCode" minOccurs="0">
           <xs:annotation>
             <xs:documentation>
               Defined by NAFTA regulations.
             </xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="ProducerId" type="xs:string" minOccurs="0">
           <xs:annotation>
             <xs:documentation>
               Identification of which producer is associated with this commodity (if multiple
               producers are used in a single shipment).
             </xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="NetCostMethod" type="ns:NaftaNetCostMethodCode" minOccurs="0">
           <xs:annotation>
             <xs:documentation/>
           </xs:annotation>
         </xs:element>
         <xs:element name="NetCostDateRange" type="ns:DateRange" minOccurs="0">
           <xs:annotation>
             <xs:documentation>
               Date range over which RVC net cost was calculated.
             </xs:documentation>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:complexType name="DateRange">
       <xs:annotation>
         <xs:documentation/>
       </xs:annotation>
       <xs:sequence>
         <xs:element name="Begins" type="xs:date">
           <xs:annotation>
             <xs:documentation>The beginning date in a date range.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="Ends" type="xs:date">
           <xs:annotation>
             <xs:documentation>The end date in a date range.</xs:documentation>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:simpleType name="NaftaImportSpecificationType">
       <xs:annotation>
         <xs:documentation/>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="IMPORTER_OF_RECORD"/>
         <xs:enumeration value="RECIPIENT"/>
         <xs:enumeration value="UNKNOWN"/>
         <xs:enumeration value="VARIOUS"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:simpleType name="NaftaNetCostMethodCode">
       <xs:annotation>
         <xs:documentation>
           Net cost method used.
         </xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="NC"/>
         <xs:enumeration value="NO"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:simpleType name="NaftaPreferenceCriterionCode">
       <xs:annotation>
         <xs:documentation>
           See instructions for NAFTA Certificate of Origin for code definitions.
         </xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="A"/>
         <xs:enumeration value="B"/>
         <xs:enumeration value="C"/>
         <xs:enumeration value="D"/>
         <xs:enumeration value="E"/>
         <xs:enumeration value="F"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:complexType name="NaftaProducer">
       <xs:annotation>
         <xs:documentation/>
       </xs:annotation>
       <xs:sequence>
         <xs:element name="Id" type="xs:string" minOccurs="0">
           <xs:annotation>
             <xs:documentation/>
           </xs:annotation>
         </xs:element>
         <xs:element name="Producer" type="ns:Party" minOccurs="0">
           <xs:annotation>
             <xs:documentation/>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:simpleType name="NaftaProducerDeterminationCode">
       <xs:annotation>
         <xs:documentation>
           See instructions for NAFTA Certificate of Origin for code definitions.
         </xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="NO_1"/>
         <xs:enumeration value="NO_2"/>
         <xs:enumeration value="NO_3"/>
         <xs:enumeration value="YES"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:simpleType name="NaftaProducerSpecificationType">
       <xs:annotation>
         <xs:documentation/>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="AVAILABLE_UPON_REQUEST"/>
         <xs:enumeration value="MULTIPLE_SPECIFIED"/>
         <xs:enumeration value="SAME"/>
         <xs:enumeration value="SINGLE_SPECIFIED"/>
         <xs:enumeration value="UNKNOWN"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:simpleType name="DropoffType">
       <xs:annotation>
         <xs:documentation>Identifies the method by which the package is to be tendered to FedEx. This element does not dispatch a courier for package pickup.</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="BUSINESS_SERVICE_CENTER"/>
         <xs:enumeration value="DROP_BOX"/>
         <xs:enumeration value="REGULAR_PICKUP"/>
         <xs:enumeration value="REQUEST_COURIER"/>
         <xs:enumeration value="STATION"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:simpleType name="ServiceType">
       <xs:annotation>
         <xs:documentation>Identifies the FedEx service to use in shipping the package. See ServiceType for list of valid enumerated values.</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="EUROPE_FIRST_INTERNATIONAL_PRIORITY"/>
         <xs:enumeration value="FEDEX_1_DAY_FREIGHT"/>
         <xs:enumeration value="FEDEX_2_DAY"/>
         <xs:enumeration value="FEDEX_2_DAY_FREIGHT"/>
         <xs:enumeration value="FEDEX_3_DAY_FREIGHT"/>
         <xs:enumeration value="FEDEX_EXPRESS_SAVER"/>
         <xs:enumeration value="FEDEX_GROUND"/>
         <xs:enumeration value="FIRST_OVERNIGHT"/>
         <xs:enumeration value="GROUND_HOME_DELIVERY"/>
         <xs:enumeration value="INTERNATIONAL_ECONOMY"/>
         <xs:enumeration value="INTERNATIONAL_ECONOMY_FREIGHT"/>
         <xs:enumeration value="INTERNATIONAL_FIRST"/>
         <xs:enumeration value="INTERNATIONAL_PRIORITY"/>
         <xs:enumeration value="INTERNATIONAL_PRIORITY_FREIGHT"/>
         <xs:enumeration value="PRIORITY_OVERNIGHT"/>
         <xs:enumeration value="STANDARD_OVERNIGHT"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:simpleType name="PackagingType">
       <xs:annotation>
         <xs:documentation>Identifies the packaging used by the requestor for the package. See PackagingType for list of valid enumerated values.</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="FEDEX_10KG_BOX"/>
         <xs:enumeration value="FEDEX_25KG_BOX"/>
         <xs:enumeration value="FEDEX_BOX"/>
         <xs:enumeration value="FEDEX_ENVELOPE"/>
         <xs:enumeration value="FEDEX_PAK"/>
         <xs:enumeration value="FEDEX_TUBE"/>
         <xs:enumeration value="YOUR_PACKAGING"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:simpleType name="RateRequestType">
       <xs:annotation>
         <xs:documentation>Indicates the type of rates to be returned.</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="ACCOUNT"/>
         <xs:enumeration value="LIST"/>
         <xs:enumeration value="MULTIWEIGHT"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:simpleType name="CarrierCodeType">
       <xs:annotation>
         <xs:documentation>Identification of a FedEx operating company (transportation).</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="FDXE"/>
         <xs:enumeration value="FDXG"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:complexType name="PackageSpecialServicesRequested">
       <xs:annotation>
         <xs:documentation>Descriptive data regarding special services requested by the shipper for a shipment. If the shipper is requesting a special service which requires additional data (e.g. COD), the special service type must be present in the specialServiceTypes collection, and the supporting detail must be provided in the appropriate sub-object. For example, to request COD, "COD" must be included in the SpecialServiceTypes collection and the CodDetail object must contain the required data.</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element name="SpecialServiceTypes" type="ns:PackageSpecialServiceType" minOccurs="0" maxOccurs="unbounded">
           <xs:annotation>
             <xs:documentation>Identifies the collection of special service types requested by the shipper. See SpecialServiceTypes for the list of valid enumerated types.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="CodCollectionAmount" type="ns:Money">
           <xs:annotation>
             <xs:documentation>For use with FedEx Ground services only; COD must be present in shipment\'s special services.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="DangerousGoodsDetail" type="ns:DangerousGoodsDetail" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Descriptive data required for a FedEx shipment containing dangerous materials. This element is required when SpecialServiceType.DANGEROUS_GOODS or HAZARDOUS_MATERIAL is present in the SpecialServiceTypes collection.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="DryIceWeight" type="ns:Weight" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Descriptive data required for a FedEx shipment containing dry ice. This element is required when SpecialServiceType.DRY_ICE is present in the SpecialServiceTypes collection.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="SignatureOptionDetail" type="ns:SignatureOptionDetail" minOccurs="0">
           <xs:annotation>
             <xs:documentation>The descriptive data required for FedEx signature services. This element is required when SpecialServiceType.SIGNATURE_OPTION is present in the SpecialServiceTypes collection.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="PriorityAlertDetail" type="ns:PriorityAlertDetail" minOccurs="0">
           <xs:annotation>
             <xs:documentation>To be filled.</xs:documentation>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:complexType name="PriorityAlertDetail">
       <xs:annotation>
         <xs:documentation>Currently not supported.</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element maxOccurs="3" minOccurs="0" name="Content" type="xs:string"/>
       </xs:sequence>
     </xs:complexType>
     <xs:complexType name="ShipmentSpecialServicesRequested">
       <xs:annotation>
         <xs:documentation>Descriptive data regarding special services requested by the shipper for a shipment. If the shipper is requesting a special service which requires additional data (e.g. COD), the special service type must be present in the specialServiceTypes collection, and the supporting detail must be provided in the appropriate sub-object. For example, to request COD, "COD" must be included in the SpecialServiceTypes collection and the CodDetail object must contain the required data.</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element name="SpecialServiceTypes" type="ns:ShipmentSpecialServiceType" minOccurs="0" maxOccurs="unbounded">
           <xs:annotation>
             <xs:documentation>Identifies the collection of special service types requested by the shipper. See SpecialServiceTypes for the list of valid enumerated types.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="CodDetail" type="ns:CodDetail" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Descriptive data required for a FedEx COD (Collect-On-Delivery) shipment. This element is required when SpecialServiceType.COD is present in the SpecialServiceTypes collection.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="CodCollectionAmount" type="ns:Money">
           <xs:annotation>
             <xs:documentation>Descriptive data for the customer supplied COD collect amount. Data format for the amount element is two explicit deicmal positions (e.g. 5.00). For Express COD services only, for Ground COD services use the package level CodCollectionAmount</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="HoldAtLocationDetail" type="ns:HoldAtLocationDetail" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Descriptive data required for a FedEx shipment that is to be held at the destination FedEx location for pickup by the recipient. This element is required when SpecialServiceType.HOLD_AT_LOCATION is present in the SpecialServiceTypes collection.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="EMailNotificationDetail" type="ns:EMailNotificationDetail" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Descriptive data required for FedEx to provide email notification to the customer regarding the shipment. This element is required when SpecialServiceType.EMAIL_NOTIFICATION is present in the SpecialServiceTypes collection.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="ReturnShipmentDetail" type="ns:ReturnShipmentDetail" minOccurs="0">
           <xs:annotation>
             <xs:documentation>The descriptive data required for FedEx Printed Return Label. This element is required when SpecialServiceType.PRINTED_RETURN_LABEL is present in the SpecialServiceTypes collection</xs:documentation>
           </xs:annotation>
         </xs:element>
        <xs:element name="PendingShipmentDetail" type="ns:PendingShipmentDetail" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Details used while creating a pending shipment</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="ShipmentDryIceDetail" type="ns:ShipmentDryIceDetail" minOccurs="0">
           <xs:annotation>
             <xs:documentation>The number of packages with dry ice and the total weight of the dry ice.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="HomeDeliveryPremiumDetail" type="ns:HomeDeliveryPremiumDetail" minOccurs="0">
           <xs:annotation>
             <xs:documentation>The descriptive data required for FedEx Home Delivery options. This element is required when SpecialServiceType.HOME_DELIVERY_PREMIUM is present in the SpecialServiceTypes collection</xs:documentation>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:simpleType name="ShipmentSpecialServiceType">
       <xs:annotation>
         <xs:documentation>Identifies the collection of special service offered by FedEx.</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="BROKER_SELECT_OPTION"/>
         <xs:enumeration value="COD"/>
         <xs:enumeration value="DRY_ICE"/>
         <xs:enumeration value="EAST_COAST_SPECIAL"/>
         <xs:enumeration value="EMAIL_NOTIFICATION"/>
         <xs:enumeration value="FUTURE_DAY_SHIPMENT"/>
         <xs:enumeration value="HOLD_AT_LOCATION"/>
         <xs:enumeration value="HOLD_SATURDAY"/>
         <xs:enumeration value="HOME_DELIVERY_PREMIUM"/>
         <xs:enumeration value="INSIDE_DELIVERY"/>
         <xs:enumeration value="INSIDE_PICKUP"/>
         <xs:enumeration value="PENDING_COMPLETE"/>
         <xs:enumeration value="PENDING_SHIPMENT"/>
         <xs:enumeration value="RETURN_SHIPMENT"/>
         <xs:enumeration value="SATURDAY_DELIVERY"/>
         <xs:enumeration value="SATURDAY_PICKUP"/>
         <xs:enumeration value="THIRD_PARTY_CONSIGNEE"/>
         <xs:enumeration value="WEEKDAY_DELIVERY"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:complexType name="WebAuthenticationDetail">
       <xs:annotation>
         <xs:documentation>Used in authentication of the sender\'s identity.</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element minOccurs="1" name="UserCredential" type="ns:WebAuthenticationCredential">
           <xs:annotation>
             <xs:documentation>Credential used to authenticate a specific software application. This value is provided by FedEx after registration.</xs:documentation>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:complexType name="WebAuthenticationCredential">
       <xs:annotation>
         <xs:documentation>Two part authentication string used for the sender\'s identity</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element minOccurs="1" name="Key" type="xs:string">
           <xs:annotation>
             <xs:documentation>Identifying part of authentication credential. This value is provided by FedEx after registration</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>16</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="1" name="Password" type="xs:string">
           <xs:annotation>
             <xs:documentation>Secret part of authentication key. This value is provided by FedEx after registration.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>25</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:complexType name="ClientDetail">
       <xs:annotation>
         <xs:documentation>Descriptive data identifying the client submitting the transaction.</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element minOccurs="1" name="AccountNumber" type="xs:string">
           <xs:annotation>
             <xs:documentation>The FedEx account number assigned to the customer initiating the request.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>12</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="1" name="MeterNumber" type="xs:string">
           <xs:annotation>
             <xs:documentation>Identifies the unique client device submitting the request. This number is assigned by FedEx and identifies the unique device from which the request is originating.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>10</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="Localization" type="ns:Localization">
           <xs:annotation>
             <xs:documentation>Governs any future language/translations used for human-readable Notification.localizedMessages in responses to the request containing this ClientDetail object. Different requests from the same client may contain different Localization data. (Contrast with TransactionDetail.localization, which governs data payload language/translation.)</xs:documentation>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:complexType name="Localization">
       <xs:annotation>
         <xs:documentation>Governs any future language/translations used for human-readable text.</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element minOccurs="1" name="LanguageCode" type="xs:string">
           <xs:annotation>
             <xs:documentation>Identifies the language to use for human-readable messages.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>2</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="LocaleCode" type="xs:string">
           <xs:annotation>
             <xs:documentation>Identifies the locale (i.e.  country code) associated with the language.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>2</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:complexType name="TransactionDetail">
       <xs:annotation>
         <xs:documentation>Descriptive data for this customer transaction. The TransactionDetail from the request is echoed back to the caller in the corresponding reply.</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element minOccurs="0" name="CustomerTransactionId" type="xs:string">
           <xs:annotation>
             <xs:documentation>Identifies a customer-supplied unique identifier for this transaction. It is returned in the reply message to aid in matching requests to replies.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>40</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="Localization" type="ns:Localization">
           <xs:annotation>
             <xs:documentation>Governs any future language/translations applied to the data payload.</xs:documentation>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:complexType name="VersionId">
       <xs:annotation>
         <xs:documentation>Identifies the version/level of a service operation performed by the callee (in each reply).</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element name="ServiceId" type="xs:string" minOccurs="1" fixed="crs">
           <xs:annotation>
             <xs:documentation>Identifies a system or sub-system which performs an operation.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="Major" type="xs:int" fixed="5" minOccurs="1">
           <xs:annotation>
             <xs:documentation>Identifies the service business level. For this release this value should be set to 1.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="Intermediate" type="xs:int" fixed="0" minOccurs="1">
           <xs:annotation>
             <xs:documentation>Identifies the service interface level. For this release this value should be set to 1.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="Minor" type="xs:int" fixed="0" minOccurs="1">
           <xs:annotation>
             <xs:documentation>Identifies the service code level. For this release this value should be set to 0.</xs:documentation>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:complexType name="Address">
       <xs:annotation>
         <xs:documentation>The descriptive data for a physical location.</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element maxOccurs="2" minOccurs="0" name="StreetLines" type="xs:string">
           <xs:annotation>
             <xs:documentation>Combination of number, street name, etc. At least one line is required for a valid physical address; empty lines should not be included.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>35</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="City" type="xs:string">
           <xs:annotation>
             <xs:documentation>Name of city, town, etc.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>
                 <ns:Express>35</ns:Express>
                 <ns:Ground>20</ns:Ground>
               </xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="StateOrProvinceCode" type="xs:string">
           <xs:annotation>
             <xs:documentation>Identifying abbreviation for US state, Canada province, etc. Format and presence of this field will vary, depending on country.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>2</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="PostalCode" type="xs:string">
           <xs:annotation>
             <xs:documentation>Identification of a region (usually small) for mail/package delivery. Format and presence of this field will vary, depending on country. This element is required if both the City and StateOrProvinceCode are not present.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>16</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="UrbanizationCode" type="xs:string">
           <xs:annotation>
             <xs:documentation>Relevant only to addresses in Puerto Rico. In Puerto Rico, multiple addresses within the same ZIP code can have the same house number and street name. When this is the case, the urbanization code is needed to distinguish them.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="1" name="CountryCode" type="xs:string">
           <xs:annotation>
             <xs:documentation>Identification of a country.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>2</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="Residential" type="xs:boolean">
           <xs:annotation>
             <xs:documentation>Indicates whether this address is residential (as opposed to commercial).</xs:documentation>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:complexType name="Payment">
       <xs:annotation>
         <xs:documentation>The descriptive data for the monetary compensation given to FedEx for services rendered to the customer.</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element minOccurs="0" name="PaymentType" type="ns:PaymentType">
           <xs:annotation>
             <xs:documentation>Identifies the method of payment for a service. See PaymentType for list of valid enumerated values.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="Payor" type="ns:Payor">
           <xs:annotation>
             <xs:documentation>Descriptive data identifying the party responsible for payment for a service.</xs:documentation>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:simpleType name="PaymentType">
       <xs:annotation>
         <xs:documentation>Identifies the method of payment for a service.</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="SENDER"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:complexType name="Payor">
       <xs:annotation>
         <xs:documentation>Descriptive data identifying the party responsible for payment for a service.</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element minOccurs="1" name="AccountNumber" type="xs:string">
           <xs:annotation>
             <xs:documentation>Identifies the FedEx account number assigned to the payor.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>12</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="CountryCode" type="xs:string">
           <xs:annotation>
             <xs:documentation>Identifies the country of the payor.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>2</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:complexType name="RateRequestPackageSummary">
       <xs:annotation>
         <xs:documentation>Details about a multiple piece shipment rate request. Use this to rate a total piece total weight shipemnt.</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element minOccurs="1" name="PieceCount" type="xs:positiveInteger">
           <xs:annotation>
             <xs:documentation>The total number of pieces in this shipment.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="1" name="TotalWeight" type="ns:Weight">
           <xs:annotation>
             <xs:documentation>The total weight of all the pieces in this shipment.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="PerPieceDimensions" type="ns:Dimensions">
           <xs:annotation>
             <xs:documentation>The dimensions that are to be applied to each piece in this shipment. One set of dimensions will applied to each piece.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="TotalInsuredValue" type="ns:Money">
           <xs:annotation>
             <xs:documentation>The total amount of insurance requested for this shipment. This amount has 2 explicit decimal positions and has a max length of 11 including the decimal.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="VariableHandlingChargeDetail" type="ns:VariableHandlingChargeDetail">
           <xs:annotation>
             <xs:documentation>Descriptive data providing details about how to calculate variable handling charges at the package level.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="SpecialServicesRequested" type="ns:PackageSpecialServicesRequested">
           <xs:annotation>
             <xs:documentation>Descriptive data regarding special services requested by the shipper for this shipment. If the shipper is requesting a special service which requires additional data (e.g. COD), the special service type must be present in the specialServiceTypes collection, and the supporting detail must be provided in the appropriate sub-object. For example, to request COD, "COD" must be included in the SpecialServiceTypes collection and the CodDetail object must contain the required data.</xs:documentation>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:simpleType name="OversizeClassType">
       <xs:annotation>
         <xs:documentation>The Oversize classification for a package.</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="OVERSIZE_1"/>
         <xs:enumeration value="OVERSIZE_2"/>
         <xs:enumeration value="OVERSIZE_3"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:complexType name="Weight">
       <xs:annotation>
         <xs:documentation>The descriptive data for the heaviness of an object.</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element name="Units" type="ns:WeightUnits">
           <xs:annotation>
             <xs:documentation>Identifies the unit of measure associated with a weight value. See WeightUnits for the list of valid enumerated values.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="Value" type="xs:decimal">
           <xs:annotation>
             <xs:documentation>Identifies the weight value of the package/shipment. Contains 1 explicit decimal position</xs:documentation>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:simpleType name="WeightUnits">
       <xs:annotation>
         <xs:documentation>Identifies the unit of measure associated with a weight value. See WeightUnits for the list of valid enumerated values.</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="KG"/>
         <xs:enumeration value="LB"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:complexType name="Dimensions">
       <xs:annotation>
         <xs:documentation>The dimensions of this package and the unit type used for the measurements.</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element minOccurs="1" name="Length">
           <xs:simpleType>
             <xs:restriction base="xs:nonNegativeInteger"/>
           </xs:simpleType>
         </xs:element>
         <xs:element minOccurs="1" name="Width">
           <xs:simpleType>
             <xs:restriction base="xs:nonNegativeInteger"/>
           </xs:simpleType>
         </xs:element>
         <xs:element minOccurs="1" name="Height">
           <xs:simpleType>
             <xs:restriction base="xs:nonNegativeInteger"/>
           </xs:simpleType>
         </xs:element>
         <xs:element minOccurs="1" name="Units" type="ns:LinearUnits"/>
       </xs:sequence>
     </xs:complexType>
     <xs:simpleType name="LinearUnits">
       <xs:annotation>
         <xs:documentation>CM = centimeters, IN = inches</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="CM"/>
         <xs:enumeration value="IN"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:complexType name="Money">
       <xs:annotation>
         <xs:documentation>The descriptive data for the medium of exchange for FedEx services.</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element minOccurs="1" name="Currency" type="xs:string">
           <xs:annotation>
             <xs:documentation>Identifies the currency of the monetary amount.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>3</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="1" name="Amount">
           <xs:annotation>
             <xs:documentation>Identifies the monetary amount.</xs:documentation>
           </xs:annotation>
           <xs:simpleType>
             <xs:restriction base="xs:decimal"/>
           </xs:simpleType>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:complexType name="SignatureOptionDetail">
       <xs:annotation>
         <xs:documentation>The descriptive data required for FedEx delivery signature services.</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element name="OptionType" type="ns:SignatureOptionType">
           <xs:annotation>
             <xs:documentation>Identifies the delivery signature services option selected by the customer for this shipment. See OptionType for the list of valid values.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="SignatureReleaseNumber" type="xs:string" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Identifies the delivery signature release authorization number.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>10</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:simpleType name="SignatureOptionType">
       <xs:annotation>
         <xs:documentation>Identifies the delivery signature services options offered by FedEx.</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="ADULT"/>
         <xs:enumeration value="DIRECT"/>
         <xs:enumeration value="INDIRECT"/>
         <xs:enumeration value="NO_SIGNATURE_REQUIRED"/>
         <xs:enumeration value="SERVICE_DEFAULT"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:simpleType name="PackageSpecialServiceType">
       <xs:annotation>
         <xs:documentation>Identifies the collection of special services offered by FedEx.</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="APPOINTMENT_DELIVERY"/>
         <xs:enumeration value="DANGEROUS_GOODS"/>
         <xs:enumeration value="DRY_ICE"/>
         <xs:enumeration value="NON_STANDARD_CONTAINER"/>
         <xs:enumeration value="PRIORITY_ALERT"/>
         <xs:enumeration value="SIGNATURE_OPTION"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:complexType name="CodDetail">
       <xs:annotation>
         <xs:documentation>Descriptive data required for a FedEx COD (Collect-On-Delivery) shipment.</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element minOccurs="0" name="AddTransportationCharges" type="ns:CodAddTransportationChargesType">
           <xs:annotation>
             <xs:documentation>Identifies if freight charges are to be added to the COD amount. This element determines which freight charges should be added to the COD collect amount. See CodAddTransportationChargesType for the liist of valid enumerated values.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="1" name="CollectionType" type="ns:CodCollectionType">
           <xs:annotation>
             <xs:documentation>Identifies the type of funds FedEx should collect upon package delivery. See CodCollectionType for the list of valid enumerated values.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="CodRecipient" type="ns:Party">
           <xs:annotation>
             <xs:documentation>Descriptive data about the recipient of the COD shipment.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="ReferenceIndicator" type="ns:CodReturnReferenceIndicatorType">
           <xs:annotation>
             <xs:documentation>Indicates which type of reference information to include on the COD return shipping label.</xs:documentation>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:simpleType name="CodAddTransportationChargesType">
       <xs:annotation>
         <xs:documentation>Identifies what freight charges should be added to the COD collect amount.</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="ADD_ACCOUNT_COD_SURCHARGE"/>
         <xs:enumeration value="ADD_ACCOUNT_NET_CHARGE"/>
         <xs:enumeration value="ADD_ACCOUNT_NET_FREIGHT"/>
         <xs:enumeration value="ADD_ACCOUNT_TOTAL_CUSTOMER_CHARGE"/>
         <xs:enumeration value="ADD_LIST_COD_SURCHARGE"/>
         <xs:enumeration value="ADD_LIST_NET_CHARGE"/>
         <xs:enumeration value="ADD_LIST_NET_FREIGHT"/>
         <xs:enumeration value="ADD_LIST_TOTAL_CUSTOMER_CHARGE"/>
         <xs:enumeration value="ADD_SHIPMENT_MULTIWEIGHT_NET_CHARGE"/>
         <xs:enumeration value="ADD_SHIPMENT_MULTIWEIGHT_NET_FREIGHT"/>
         <xs:enumeration value="ADD_SUM_OF_ACCOUNT_NET_CHARGES"/>
         <xs:enumeration value="ADD_SUM_OF_ACCOUNT_NET_FREIGHT"/>
         <xs:enumeration value="ADD_SUM_OF_LIST_NET_CHARGES"/>
         <xs:enumeration value="ADD_SUM_OF_LIST_NET_FREIGHT"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:simpleType name="CodCollectionType">
       <xs:annotation>
         <xs:documentation>Identifies the type of funds FedEx should collect upon package delivery.</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="ANY"/>
         <xs:enumeration value="CASH"/>
         <xs:enumeration value="GUARANTEED_FUNDS"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:simpleType name="CodReturnReferenceIndicatorType">
       <xs:annotation>
         <xs:documentation>Indicates which type of reference information to include on the COD return shipping label.</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="INVOICE"/>
         <xs:enumeration value="PO"/>
         <xs:enumeration value="REFERENCE"/>
         <xs:enumeration value="TRACKING"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:complexType name="Party">
       <xs:annotation>
         <xs:documentation>The descriptive data for a person or company entitiy doing business with FedEx.</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element minOccurs="0" name="AccountNumber" type="xs:string">
           <xs:annotation>
             <xs:documentation>Identifies the FedEx account number assigned to the customer.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>12</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="Tin" type="ns:TaxpayerIdentification">
           <xs:annotation>
             <xs:documentation>Descriptive data for taxpayer identification information.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="Contact" type="ns:Contact">
           <xs:annotation>
             <xs:documentation>Descriptive data identifying the point-of-contact person.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="Address" type="ns:Address">
           <xs:annotation>
             <xs:documentation>The descriptive data for a physical location.</xs:documentation>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:complexType name="TaxpayerIdentification">
       <xs:annotation>
         <xs:documentation>The descriptive data for taxpayer identification information.</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element minOccurs="1" name="TinType" type="ns:TinType">
           <xs:annotation>
             <xs:documentation>Identifies the category of the taxpayer identification number. See TinType for the list of values.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="1" name="Number" type="xs:string">
           <xs:annotation>
             <xs:documentation>Identifies the taxpayer identification number.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>18</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:simpleType name="TinType">
       <xs:annotation>
         <xs:documentation>Identifies the category of the taxpayer identification number.</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="EIN"/>
         <xs:enumeration value="SSN"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:complexType name="Contact">
       <xs:annotation>
         <xs:documentation>The descriptive data for a point-of-contact person.</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element minOccurs="0" name="PersonName" type="xs:string">
           <xs:annotation>
             <xs:documentation>Identifies the contact person\'s name.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>35</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="Title" type="xs:string">
           <xs:annotation>
             <xs:documentation>Identifies the contact person\'s title.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="CompanyName" type="xs:string">
           <xs:annotation>
             <xs:documentation>Identifies the contact person\'s company name.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>35</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="PhoneNumber" type="xs:string">
           <xs:annotation>
             <xs:documentation>Identifies the contact person\'s phone number.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>15</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="PhoneExtension" type="xs:string">
           <xs:annotation>
             <xs:documentation>Identifies the contact person\'s phone number extension.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="PagerNumber" type="xs:string">
           <xs:annotation>
             <xs:documentation>Identifies the contact person\'s pager number.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>15</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="FaxNumber" type="xs:string">
           <xs:annotation>
             <xs:documentation>Identifies the contact person\'s fax machine phone number.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>15</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="EMailAddress" type="xs:string">
           <xs:annotation>
             <xs:documentation>Identifies the contact person\'s email address.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>
                 <ns:Express>120</ns:Express>
                 <ns:Ground>35</ns:Ground>
               </xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:complexType name="HoldAtLocationDetail">
       <xs:annotation>
         <xs:documentation>Descriptive data required for a FedEx shipment that is to be held at the destination FedEx location for pickup by the recipient.</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element minOccurs="0" name="PhoneNumber" type="xs:string">
           <xs:annotation>
             <xs:documentation>Identifies a telephone number.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>15</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="Address" type="ns:Address">
           <xs:annotation>
             <xs:documentation>The descriptive data for a physical location.</xs:documentation>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:complexType name="DangerousGoodsDetail">
       <xs:annotation>
         <xs:documentation>The descriptive data required for a FedEx shipment containing dangerous goods (hazardous materials).</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element minOccurs="0" name="Accessibility" type="ns:DangerousGoodsAccessibilityType">
           <xs:annotation>
             <xs:documentation>Identifies whether or not the products being shipped are required to be accessible during delivery.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="CargoAircraftOnly" type="xs:boolean">
           <xs:annotation>
             <xs:documentation>Shipment is packaged/documented for movement ONLY on cargo aircraft.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="HazMatCertificateData" type="ns:HazMatCertificateData">
           <xs:annotation>
             <xs:documentation> to be included in the OP-950 (Hazardous Materials Certificate) returned in Close reply</xs:documentation>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:simpleType name="DangerousGoodsAccessibilityType">
       <xs:annotation>
         <xs:documentation>Identifies whether or not the products being shipped are required to be accessible during delivery.</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="ACCESSIBLE"/>
         <xs:enumeration value="INACCESSIBLE"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:complexType name="HazMatCertificateData">
       <xs:annotation>
         <xs:documentation> to be included in the OP-950 (Hazardous Materials Certificate) returned in Close reply</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element maxOccurs="3" minOccurs="0" name="DotProperShippingName" type="xs:string">
           <xs:annotation>
             <xs:documentation> which broad class (as established by the United States DOT) the contents of this shipment falls into; The user should be aware that these (up to three) 50-character elements will actually be formatted on the certificate in a 25-character-wide column on up to six lines; Up to 25 characters of the first element will appear on the first line, and any additional characters starting with the 26th will appear on a second line.  The first 25 of the second element, if it exists, will appear on a third line, and any additional characters starting with the 26th will appear on the fourth line. The first 25 characters of the third element will appear on a fifth line, and any additional characters starting with the 26th will appear on a sixth line.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>50</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="DotHazardClassOrDivision" type="xs:string">
           <xs:annotation>
             <xs:documentation> which broad class (as established by the United States Department of Transportation) the contents of this shipment falls into.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>25</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="DotIdNumber" type="xs:string">
           <xs:annotation>
             <xs:documentation>ID Number (UN or NA number), including prefix.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>11</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="DotLabelType" type="xs:string">
           <xs:annotation>
             <xs:documentation> Type of D.O.T. diamond hazard label, or "Ltd. Qty.", or Exemption Number.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>50</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="PackingGroup" type="xs:string">
           <xs:annotation>
             <xs:documentation> materials in certain classes, the Packing Group signifies the degree of hazard.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>3</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="Quantity" type="xs:decimal">
           <xs:annotation>
             <xs:documentation>Quantity (in the given unites) of dangerous goods in shipment.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="Units" type="xs:string">
           <xs:annotation>
             <xs:documentation>Units in which the Quantity is expressed.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>4</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="TwentyFourHourEmergencyResponseContactNumber" type="xs:string">
           <xs:annotation>
             <xs:documentation>24-hour emergency response contact phone number.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>15</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="TwentyFourHourEmergencyResponseContactName" type="xs:string">
           <xs:annotation>
             <xs:documentation>24-hour emergency response contact name.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>50</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:complexType name="EMailNotificationDetail">
       <xs:annotation>
         <xs:documentation>The descriptive data required for FedEx to provide email notification to the customer regarding the shipment.</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element minOccurs="0" name="PersonalMessage" type="xs:string">
           <xs:annotation>
             <xs:documentation>Identifies the message text to be sent in the email notification.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>120</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
         <xs:element maxOccurs="6" minOccurs="0" name="Recipients" type="ns:EMailNotificationRecipient">
           <xs:annotation>
             <xs:documentation>The descriptive data element for the collection of email recipients.</xs:documentation>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:complexType name="EMailNotificationRecipient">
       <xs:annotation>
         <xs:documentation>The descriptive data for a FedEx email notification recipient.</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element name="EMailNotificationRecipientType" type="ns:EMailNotificationRecipientType">
           <xs:annotation>
             <xs:documentation>Identifies the email notification recipient type. See EMailNotificationRecipientType for a list of valid enumerated values.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="EMailAddress" type="xs:string">
           <xs:annotation>
             <xs:documentation>Identifies the email address of the notification recipient.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>
                 <ns:Express>120</ns:Express>
                 <ns:Ground>35</ns:Ground>
               </xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="NotifyOnShipment" type="xs:boolean">
           <xs:annotation>
             <xs:documentation>Identifies if an email notification should be sent to the recipient when the package is shipped.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="NotifyOnException" type="xs:boolean">
           <xs:annotation>
             <xs:documentation>Identifies if an email notification should be sent to the recipient when an exception occurs during package movement from origin to destination.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="NotifyOnDelivery" type="xs:boolean">
           <xs:annotation>
             <xs:documentation>Identifies if an email notification should be sent to the recipient when the package is delivered.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="Format" type="ns:EMailNotificationFormatType">
           <xs:annotation>
             <xs:documentation>A unique format can be specified for each email address indicated. The format will apply to notification emails sent to a particular email address..</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="Localization" type="ns:Localization">
           <xs:annotation>
             <xs:documentation>Indicates the language the notification is expressed in.</xs:documentation>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:simpleType name="EMailNotificationRecipientType">
       <xs:annotation>
         <xs:documentation>Identifies the set of valid email notification recipient types. For SHIPPER, RECIPIENT and BROKER the email address asssociated with their definitions will be used, any email address sent with the email notification for these three email notification recipient types will be ignored.</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="BROKER"/>
         <xs:enumeration value="OTHER"/>
         <xs:enumeration value="RECIPIENT"/>
         <xs:enumeration value="SHIPPER"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:simpleType name="EMailNotificationFormatType">
       <xs:annotation>
         <xs:documentation>A unique format can be specified for each email address indicated. The format will apply to notification emails sent to a particular email address..</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="HTML"/>
         <xs:enumeration value="TEXT"/>
         <xs:enumeration value="WIRELESS"/>
       </xs:restriction>
     </xs:simpleType>
<xs:complexType name="ReturnEMailDetail">
       <xs:annotation>
         <xs:documentation></xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element minOccurs="0" name="MerchantPhoneNumber" type="xs:string">
           <xs:annotation>
             <xs:documentation></xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="AllowedSpecialServices" type="ns:ReturnEMailAllowedSpecialServiceType">
           <xs:annotation>
             <xs:documentation></xs:documentation>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:complexType name="ReturnShipmentDetail">
       <xs:annotation>
         <xs:documentation>Information relating to a return shipment.</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element name="ReturnType" type="ns:ReturnType">
           <xs:annotation>
             <xs:documentation>The type of return shipment that is being requested. At present the only type of retrun shipment that is supported is PRINT_RETURN_LABEL. With this option you can print a return label to insert into the box of an outbound shipment. This option can not be used to print an outbound label.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="Rma" type="ns:Rma">
           <xs:annotation>
             <xs:documentation>Return Merchant Authorization</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="ReturnEMailDetai" type="ns:ReturnEMailDetail">
           <xs:annotation>
             <xs:documentation>Specific information about the delivery of the email and options for the shipment.</xs:documentation>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:simpleType name="ReturnType">
       <xs:annotation>
         <xs:documentation>The type of return shipment that is being requested.</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="FEDEX_TAG"/>
    <xs:enumeration value="PENDING"/>
         <xs:enumeration value="PRINT_RETURN_LABEL"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:complexType name="Rma">
       <xs:annotation>
         <xs:documentation>Return Merchant Authorization</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element minOccurs="1" name="Number" type="xs:string">
           <xs:annotation>
             <xs:documentation>Return Merchant Authorization Number</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>20</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="Reason" type="xs:string">
           <xs:annotation>
             <xs:documentation>The reason for the return.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>60</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:complexType name="EMailLabelDetail">
       <xs:annotation>
         <xs:documentation>Specific information about the delivery of the email and options for the shipment.</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element name="NotificationEMailAddress" type="xs:string">
           <xs:annotation>
             <xs:documentation>Email address to send the URL to.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="NotificationMessage" type="xs:string" minOccurs="0">
           <xs:annotation>
             <xs:documentation>A message to be inserted into the email.</xs:documentation>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:simpleType name="ReturnEMailAllowedSpecialServiceType">
       <xs:annotation>
         <xs:documentation>Special services the requestor will allow for this shipment.</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="SATURDAY_DELIVERY"/>
         <xs:enumeration value="SATURDAY_PICKUP"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:complexType name="ShipmentDryIceDetail">
       <xs:annotation>
         <xs:documentation>The number of packages with dry ice and the total weight of the dry ice.</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element minOccurs="0" name="PackageCount" type="xs:nonNegativeInteger">
           <xs:annotation>
             <xs:documentation>The number of packages in this shipment that contain dry ice.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="TotalWeight" type="ns:Weight">
           <xs:annotation>
             <xs:documentation>The total weight of the dry ice in this shipment.</xs:documentation>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:complexType name="HomeDeliveryPremiumDetail">
       <xs:annotation>
         <xs:documentation>The descriptive data required by FedEx for home delivery services.</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element minOccurs="1" name="HomeDeliveryPremiumType" type="ns:HomeDeliveryPremiumType">
           <xs:annotation>
             <xs:documentation>The type of Home Delivery Premium service being requested.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="Date" type="xs:date">
           <xs:annotation>
             <xs:documentation>Required for Date Certain Home Delivery.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="PhoneNumber" type="xs:string">
           <xs:annotation>
             <xs:documentation>Required for Date Certain and Appointment Home Delivery.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>15</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:simpleType name="HomeDeliveryPremiumType">
       <xs:annotation>
         <xs:documentation>The type of Home Delivery Premium service being requested.</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="APPOINTMENT"/>
         <xs:enumeration value="DATE_CERTAIN"/>
         <xs:enumeration value="EVENING"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:complexType name="TrackingId">
       <xs:sequence>
         <xs:element name="FormId" type="xs:string" minOccurs="0"/>
         <xs:element name="TrackingNumber" type="xs:string"/>
       </xs:sequence>
     </xs:complexType>
     <xs:complexType name="RequestedPackage">
       <xs:sequence>
         <xs:element name="SequenceNumber" type="xs:positiveInteger" minOccurs="0">
           <xs:annotation>
             <xs:documentation>The package sequence number of this package in a multiple piece shipment.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="VariableHandlingChargeDetail" type="ns:VariableHandlingChargeDetail" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Details about how to calculate variable handling charges at the package level.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="InsuredValue" type="ns:Money" minOccurs="0">
           <xs:annotation>
             <xs:documentation>The amount of insurance requested for this package. This amount has 2 explicit decimal positions and has a max length of 11 including the decimal.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="Weight" type="ns:Weight">
           <xs:annotation>
             <xs:documentation>The total wight of this package. This value has 1 explicit decimal poistion and has a max length of 12 including the decimal.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="Dimensions" type="ns:Dimensions" minOccurs="0">
           <xs:annotation>
             <xs:documentation>The dimensions of this package and the unit type used for the measurements.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="ItemDescription" type="xs:string">
           <xs:annotation>
             <xs:documentation>The description that is associated with the tracking number on the web site when printing email labels.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="CustomerReferences" type="ns:CustomerReference" minOccurs="0" maxOccurs="3">
           <xs:annotation>
             <xs:documentation>Reference information to be associated with this package.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="SpecialServicesRequested" type="ns:PackageSpecialServicesRequested" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Currently not supported.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="ContentRecords" type="ns:ContentRecord" minOccurs="0" maxOccurs="unbounded">
           <xs:annotation>
             <xs:documentation>Currently not supported.</xs:documentation>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:complexType name="ContentRecord">
       <xs:annotation>
         <xs:documentation>??</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element name="PartNumber" type="xs:string" minOccurs="0">
           <xs:annotation>
             <xs:documentation>???</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="ItemNumber" type="xs:string" minOccurs="0">
           <xs:annotation>
             <xs:documentation>???</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="ReceivedQuantity" type="xs:nonNegativeInteger" minOccurs="0">
           <xs:annotation>
             <xs:documentation>???</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="Description" type="xs:string" minOccurs="0">
           <xs:annotation>
             <xs:documentation>???</xs:documentation>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:complexType name="CustomerReference">
       <xs:annotation>
         <xs:documentation>Reference information to be associated with this package.</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element name="CustomerReferenceType" type="ns:CustomerReferenceType">
           <xs:annotation>
             <xs:documentation>The reference type to be associated with this reference data.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="Value" type="xs:string"/>
       </xs:sequence>
     </xs:complexType>
     <xs:simpleType name="CustomerReferenceType">
       <xs:annotation>
         <xs:documentation>The types of references available for use.</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="BILL_OF_LADING"/>
         <xs:enumeration value="CUSTOMER_REFERENCE"/>
         <xs:enumeration value="DEPARTMENT_NUMBER"/>
         <xs:enumeration value="ELECTRONIC_PRODUCT_CODE"/>
         <xs:enumeration value="INVOICE_NUMBER"/>
         <xs:enumeration value="P_O_NUMBER"/>
         <xs:enumeration value="SHIPMENT_INTEGRITY"/>
         <xs:enumeration value="STORE_NUMBER"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:complexType name="VariableHandlingChargeDetail">
       <xs:annotation>
         <xs:documentation>Details about how to calculate variable handling charges at the shipment level.</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element minOccurs="1" name="VariableHandlingChargeType" type="ns:VariableHandlingChargeType">
           <xs:annotation>
             <xs:documentation>The type of handling charge to be calculated and returned in the reply.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="FixedValue" type="ns:Money">
           <xs:annotation>
             <xs:documentation>Used with Variable handling charge type of FIXED_VALUE.
											  Contains the amount to be added to the freight charge.
											  Contains 2 explicit decimal positions with a total max length of 10 including the decimal.
				</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="PercentValue" type="xs:decimal">
           <xs:annotation>
             <xs:documentation>Used with Variable handling charge types PERCENTAGE_OF_BASE, PERCENTAGE_OF_NET or PERCETAGE_OF_NET_EXCL_TAXES.
											  Used to calculate the amount to be added to the freight charge.
											  Contains 2 explicit decimal positions.
				</xs:documentation>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:simpleType name="VariableHandlingChargeType">
       <xs:annotation>
         <xs:documentation>The type of handling charge to be calculated and returned in the reply.</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="FIXED_AMOUNT"/>
         <xs:enumeration value="PERCENTAGE_OF_NET_CHARGE"/>
         <xs:enumeration value="PERCENTAGE_OF_NET_CHARGE_EXCLUDING_TAXES"/>
         <xs:enumeration value="PERCENTAGE_OF_NET_FREIGHT"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:complexType name="RateReply">
       <xs:sequence>
         <xs:element minOccurs="1" name="HighestSeverity" type="ns:NotificationSeverityType">
           <xs:annotation>
             <xs:documentation>This indicates the highest level of severity of all the notifications returned in this reply</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element maxOccurs="unbounded" minOccurs="1" name="Notifications" type="ns:Notification">
           <xs:annotation>
             <xs:documentation>The descriptive data regarding the results of the submitted transaction.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="TransactionDetail" type="ns:TransactionDetail">
           <xs:annotation>
             <xs:documentation>Descriptive data for this customer transaction. The TransactionDetail from the request is echoed back to the caller in the corresponding reply.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="Version" type="ns:VersionId">
           <xs:annotation>
             <xs:documentation>Identifies the version/level of a service operation expected by a caller (in each request) and performed by the callee (in each reply).</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="RateReplyDetails" type="ns:RateReplyDetail" maxOccurs="unbounded" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Rate information which was requested.</xs:documentation>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:complexType name="RateReplyDetail">
       <xs:sequence>
         <xs:element name="ServiceType" type="ns:ServiceType" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Identifies the FedEx service to use in shipping the package. See ServiceType for list of valid enumerated values.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="PackagingType" type="ns:PackagingType" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Identifies the packaging used by the requestor for the package. See PackagingType for list of valid enumerated values.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="AppliedOptions" type="ns:ServiceOptionType" maxOccurs="unbounded" minOccurs="0">
         </xs:element>
         <xs:element name="DeliveryStation" type="xs:string" minOccurs="0">
           <xs:annotation>
             <xs:documentation>???</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="DeliveryDayOfWeek" type="ns:DayOfWeekType" minOccurs="0">
           <xs:annotation>
             <xs:documentation>???</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="DeliveryTimestamp" type="xs:dateTime" minOccurs="0">
           <xs:annotation>
             <xs:documentation>???</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="DestinationAirportId" type="xs:string" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Identification of an airport, using standard three-letter abbreviations.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="CommitDetails" type="ns:CommitDetail" minOccurs="0" maxOccurs="unbounded">
           <xs:annotation>
             <xs:documentation>???</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="IneligibleForMoneyBackGuarantee" type="xs:boolean">
           <xs:annotation>
             <xs:documentation>Indicates whether or not this shipment is eligible for a money back guarantee.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="OriginServiceArea" type="xs:string">
           <xs:annotation>
             <xs:documentation>Commitment code for the origin.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="DestinationServiceArea" type="xs:string">
           <xs:annotation>
             <xs:documentation>Commitment code for the destination.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="TransitTime" type="ns:TransitTimeType" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Time in transit from pickup to delivery.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="SignatureOption" type="ns:SignatureOptionType">
           <xs:annotation>
             <xs:documentation>The signature option for this package.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="ActualRateType" type="ns:ReturnedRateType">
           <xs:annotation>
             <xs:documentation>The actual rate type of the charges for this package.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element maxOccurs="unbounded" minOccurs="0" name="RatedShipmentDetails" type="ns:RatedShipmentDetail">
           <xs:annotation>
             <xs:documentation>Rate information which was requested.</xs:documentation>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:complexType name="CommitDetail">
       <xs:annotation>
         <xs:documentation>???</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element name="CommodityName" type="xs:string" minOccurs="0">
           <xs:annotation>
             <xs:documentation>???</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="ServiceType" type="ns:ServiceType" minOccurs="0">
           <xs:annotation>
             <xs:documentation>???</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="CommitTimestamp" type="xs:dateTime" minOccurs="0">
           <xs:annotation>
             <xs:documentation>???</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="DayOfWeek" type="ns:DayOfWeekType" minOccurs="0">
           <xs:annotation>
             <xs:documentation>???</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="TransitTime" type="ns:TransitTimeType" minOccurs="0">
           <xs:annotation>
             <xs:documentation>???</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="DestinationServiceArea" type="xs:string" minOccurs="0">
           <xs:annotation>
             <xs:documentation>???</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="BrokerAddress" type="ns:Address" minOccurs="0">
           <xs:annotation>
             <xs:documentation>???</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="BrokerLocationId" type="xs:string" minOccurs="0">
           <xs:annotation>
             <xs:documentation>???</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="BrokerCommitTimestamp" type="xs:dateTime" minOccurs="0">
           <xs:annotation>
             <xs:documentation>???</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="BrokerCommitDayOfWeek" type="ns:DayOfWeekType" minOccurs="0">
           <xs:annotation>
             <xs:documentation>???</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="BrokerToDestinationDays" type="xs:nonNegativeInteger" minOccurs="0">
           <xs:annotation>
             <xs:documentation>???</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="ProofOfDeliveryDate" type="xs:date" minOccurs="0">
           <xs:annotation>
             <xs:documentation>???</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="ProofOfDeliveryDayOfWeek" type="ns:DayOfWeekType" minOccurs="0">
           <xs:annotation>
             <xs:documentation>???</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="CommitMessages" type="ns:Notification" minOccurs="0" maxOccurs="unbounded">
           <xs:annotation>
             <xs:documentation>???</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="DeliveryMessages" type="xs:string" minOccurs="0" maxOccurs="unbounded">
           <xs:annotation>
             <xs:documentation>???</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="DelayDetails" type="ns:DelayDetail" minOccurs="0" maxOccurs="unbounded">
           <xs:annotation>
             <xs:documentation>???</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="RequiredDocuments" type="ns:RequiredShippingDocumentType" minOccurs="0" maxOccurs="unbounded">
           <xs:annotation>
             <xs:documentation>???</xs:documentation>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:complexType name="DelayDetail">
       <xs:sequence>
         <xs:element name="Date" type="xs:date" minOccurs="0">
           <xs:annotation>
             <xs:documentation>??</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="DayOfWeek" type="ns:DayOfWeekType" minOccurs="0">
           <xs:annotation>
             <xs:documentation>??</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="Level" type="ns:DelayLevelType" minOccurs="0">
           <xs:annotation>
             <xs:documentation>??</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="Point" type="ns:DelayPointType" minOccurs="0">
           <xs:annotation>
             <xs:documentation>??</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="Type" type="ns:CommitmentDelayType" minOccurs="0">
           <xs:annotation>
             <xs:documentation>??</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="Description" type="xs:string" minOccurs="0">
           <xs:annotation>
             <xs:documentation>??</xs:documentation>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:simpleType name="DayOfWeekType">
       <xs:annotation>
         <xs:documentation>Valid values for DayofWeekType</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="FRI"/>
         <xs:enumeration value="MON"/>
         <xs:enumeration value="SAT"/>
         <xs:enumeration value="SUN"/>
         <xs:enumeration value="THU"/>
         <xs:enumeration value="TUE"/>
         <xs:enumeration value="WED"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:simpleType name="DelayLevelType">
       <xs:annotation>
         <xs:documentation>??</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="CITY"/>
         <xs:enumeration value="COUNTRY"/>
         <xs:enumeration value="LOCATION"/>
         <xs:enumeration value="POSTAL_CODE"/>
         <xs:enumeration value="SERVICE_AREA"/>
         <xs:enumeration value="SERVICE_AREA_SPECIAL_SERVICE"/>
         <xs:enumeration value="SPECIAL_SERVICE"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:simpleType name="DelayPointType">
       <xs:annotation>
         <xs:documentation>??</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="BROKER"/>
         <xs:enumeration value="DESTINATION"/>
         <xs:enumeration value="ORIGIN"/>
         <xs:enumeration value="ORIGIN_DESTINATION_PAIR"/>
         <xs:enumeration value="PROOF_OF_DELIVERY_POINT"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:simpleType name="CommitmentDelayType">
       <xs:annotation>
         <xs:documentation>??</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="HOLIDAY"/>
         <xs:enumeration value="NON_WORKDAY"/>
         <xs:enumeration value="NO_CITY_DELIVERY"/>
         <xs:enumeration value="NO_HOLD_AT_LOCATION"/>
         <xs:enumeration value="NO_LOCATION_DELIVERY"/>
         <xs:enumeration value="NO_SERVICE_AREA_DELIVERY"/>
         <xs:enumeration value="NO_SERVICE_AREA_SPECIAL_SERVICE_DELIVERY"/>
         <xs:enumeration value="NO_SPECIAL_SERVICE_DELIVERY"/>
         <xs:enumeration value="NO_ZIP_DELIVERY"/>
         <xs:enumeration value="WEEKEND"/>
         <xs:enumeration value="WEEKEND_SPECIAL"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:simpleType name="RequiredShippingDocumentType">
       <xs:annotation>
         <xs:documentation>??</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="CANADIAN_B13A"/>
         <xs:enumeration value="CERTIFICATE_OF_ORIGIN"/>
         <xs:enumeration value="COMMERCIAL_INVOICE"/>
         <xs:enumeration value="INTERNATIONAL_AIRWAY_BILL"/>
         <xs:enumeration value="MAIL_SERVICE_AIRWAY_BILL"/>
         <xs:enumeration value="SHIPPERS_EXPORT_DECLARATION"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:simpleType name="TransitTimeType">
       <xs:annotation>
         <xs:documentation>Time in transit from pickup to delivery.</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="ONE_DAY"/>
         <xs:enumeration value="TWO_DAYS"/>
         <xs:enumeration value="THREE_DAYS"/>
         <xs:enumeration value="FOUR_DAYS"/>
         <xs:enumeration value="FIVE_DAYS"/>
         <xs:enumeration value="SIX_DAYS"/>
         <xs:enumeration value="SEVEN_DAYS"/>
         <xs:enumeration value="EIGHT_DAYS"/>
         <xs:enumeration value="NINE_DAYS"/>
         <xs:enumeration value="TEN_DAYS"/>
         <xs:enumeration value="ELEVEN_DAYS"/>
         <xs:enumeration value="TWELVE_DAYS"/>
         <xs:enumeration value="THIRTEEN_DAYS"/>
         <xs:enumeration value="FOURTEEN_DAYS"/>
         <xs:enumeration value="FIFTEEN_DAYS"/>
         <xs:enumeration value="SIXTEEN_DAYS"/>
         <xs:enumeration value="SEVENTEEN_DAYS"/>
         <xs:enumeration value="EIGHTEEN_DAYS"/>
         <xs:enumeration value="NINETEEN_DAYS"/>
         <xs:enumeration value="TWENTY_DAYS"/>
         <xs:enumeration value="UNKNOWN"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:complexType name="Notification">
       <xs:annotation>
         <xs:documentation>The descriptive data regarding the results of the submitted transaction.</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element minOccurs="1" name="Severity" type="ns:NotificationSeverityType">
           <xs:annotation>
             <xs:documentation>The severity of this notification. this can indicate success or failure or some other information about the request such as errors or notes.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="1" name="Source" type="xs:string">
           <xs:annotation>
             <xs:documentation>Indicates the source of the notification. Combined with Code, it uniqely identifies this message.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="Code" type="xs:string">
           <xs:annotation>
             <xs:documentation>A code that represents this notification. Combined with Source, it uniqely identifies this message.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>8</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="Message" type="xs:string">
           <xs:annotation>
             <xs:documentation>Text that explains this notification.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>255</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="LocalizedMessage" type="xs:string">
           <xs:annotation>
             <xs:documentation>A translated message. The translation is based on the Localization element of the ClientDetail element of the request.  Not currently supported.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element maxOccurs="unbounded" minOccurs="0" name="MessageParameters" type="ns:NotificationParameter">
           <xs:annotation>
             <xs:documentation>If the message used parameter replacement to be specific as to thre meaning of the message, this is the list of parameters that were used.</xs:documentation>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:complexType name="NotificationParameter">
       <xs:sequence>
         <xs:element name="Id" type="xs:string" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Name identifiying the type of the data in the element \'Value\'</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="Value" type="xs:string" minOccurs="0">
           <xs:annotation>
             <xs:documentation>The value that was used as the replacement parameter.</xs:documentation>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:simpleType name="NotificationSeverityType">
       <xs:annotation>
         <xs:documentation>Identifies the set of severity values for a Notification.</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="SUCCESS"/>
         <xs:enumeration value="NOTE"/>
         <xs:enumeration value="WARNING"/>
         <xs:enumeration value="ERROR"/>
         <xs:enumeration value="FAILURE"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:complexType name="RatedShipmentDetail">
       <xs:sequence>
         <xs:element minOccurs="0" name="EffectiveNetDiscount" type="ns:Money">
           <xs:annotation>
             <xs:documentation>The difference between account based rates and list rates. Only returned when list rates are requested.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="AdjustedCodCollectionAmount" type="ns:Money">
           <xs:annotation>
             <xs:documentation>Ground COD is package level.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="ShipmentRateDetail" type="ns:ShipmentRateDetail">
           <xs:annotation>
             <xs:documentation/>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" maxOccurs="unbounded" name="RatedPackages" type="ns:RatedPackageDetail">
           <xs:annotation>
             <xs:documentation/>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:simpleType name="ServiceOptionType">
       <xs:restriction base="xs:string">
         <xs:enumeration value="SATURDAY_DELIVERY"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:complexType name="ShipmentRateDetail">
       <xs:annotation>
         <xs:documentation>Shipment level rate information. Currently this is the same as the package level rate information.</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element name="RateType" type="ns:ReturnedRateType" minOccurs="0">
           <xs:annotation>
             <xs:documentation>The type of rates this information contains either account based or list rates.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="RateScale" type="xs:string" minOccurs="0">
           <xs:annotation>
             <xs:documentation>The FedEx rate scale used to calculate these rates.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>5</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
         <xs:element name="RateZone" type="xs:string" minOccurs="0">
           <xs:annotation>
             <xs:documentation>The FedEx rate zone used to calculate these rates.</xs:documentation>
             <xs:appinfo>
               <xs:MaxLength>1</xs:MaxLength>
             </xs:appinfo>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="PricingCode" type="ns:PricingCodeType">
           <xs:annotation>
             <xs:documentation>Indicates the type of pricing used for this shipment.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="CurrencyExchangeRate" type="ns:CurrencyExchangeRate">
           <xs:annotation>
             <xs:documentation>Specifies the currency exchange performed on financial amounts for this rate.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" maxOccurs="unbounded" name="SpecialRatingApplied" type="ns:SpecialRatingAppliedType">
           <xs:annotation>
             <xs:documentation>Indicates which special rating cases applied to this shipment.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="DimDivisor" type="xs:nonNegativeInteger">
           <xs:annotation>
             <xs:documentation>The value used to calculate the weight based on the dimensions.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="FuelSurchargePercent" type="xs:decimal">
           <xs:annotation>
             <xs:documentation/>
           </xs:annotation>
         </xs:element>
         <xs:element name="TotalBillingWeight" type="ns:Weight" minOccurs="0">
           <xs:annotation>
             <xs:documentation>The weight used to calculate these rates.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="TotalDimWeight" type="ns:Weight" minOccurs="0">
           <xs:annotation>
             <xs:documentation>The dimensional weith used to calculate these rates, if applicible.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="TotalBaseCharge" type="ns:Money" minOccurs="0">
           <xs:annotation>
             <xs:documentation/>
           </xs:annotation>
         </xs:element>
         <xs:element name="TotalFreightDiscounts" type="ns:Money" minOccurs="0">
           <xs:annotation>
             <xs:documentation>The total discounts used in the rate calculation.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="TotalNetFreight" type="ns:Money" minOccurs="0">
           <xs:annotation>
             <xs:documentation>The freight charge minus discounts.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="TotalSurcharges" type="ns:Money" minOccurs="0">
           <xs:annotation>
             <xs:documentation>The total amount of all surcharges applied to this shipment.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="TotalNetFedExCharge" type="ns:Money" minOccurs="0"/>
         <xs:element name="TotalTaxes" type="ns:Money" minOccurs="0">
           <xs:annotation>
             <xs:documentation>The total amount of all taxes applied to this shipment. Currently not supported.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="TotalNetCharge" type="ns:Money" minOccurs="0">
           <xs:annotation>
             <xs:documentation>The net charge after applying all discounts and surcharges.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="TotalRebates" type="ns:Money" minOccurs="0">
           <xs:annotation>
             <xs:documentation>The total sum of all rebates applied to this shipment.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="FreightDiscounts" type="ns:RateDiscount" minOccurs="0" maxOccurs="unbounded">
           <xs:annotation>
             <xs:documentation>A list of discounts that were applied to this package.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="Rebates" type="ns:Rebate" minOccurs="0" maxOccurs="unbounded">
           <xs:annotation>
             <xs:documentation>A list of the surcharges applied to this package.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="Surcharges" type="ns:Surcharge" minOccurs="0" maxOccurs="unbounded">
           <xs:annotation>
             <xs:documentation>A list of the surcharges applied to this package.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="Taxes" type="ns:Tax" minOccurs="0" maxOccurs="unbounded">
           <xs:annotation>
             <xs:documentation>A list of the taxes applied to this package.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="VariableHandlingCharges" type="ns:VariableHandlingCharges" minOccurs="0">
           <xs:annotation>
             <xs:documentation>The variable handling charges calculated based on the type variable handling charges requested.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="TotalVariableHandlingCharges" type="ns:VariableHandlingCharges" minOccurs="0">
           <xs:annotation>
             <xs:documentation>The total of all variable handling charges at both shipment (order) and package level.</xs:documentation>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:simpleType name="ReturnedRateType">
       <xs:restriction base="xs:string">
         <xs:enumeration value="PAYOR_ACCOUNT"/>
         <xs:enumeration value="PAYOR_LIST"/>
         <xs:enumeration value="PAYOR_MULTIWEIGHT"/>
         <xs:enumeration value="RATED_ACCOUNT"/>
         <xs:enumeration value="RATED_LIST"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:simpleType name="PricingCodeType">
       <xs:restriction base="xs:string">
         <xs:enumeration value="ACTUAL"/>
         <xs:enumeration value="ALTERNATE"/>
         <xs:enumeration value="BASE"/>
         <xs:enumeration value="HUNDREDWEIGHT"/>
         <xs:enumeration value="HUNDREDWEIGHT_ALTERNATE"/>
         <xs:enumeration value="INTERNATIONAL_DISTRIBUTION"/>
         <xs:enumeration value="INTERNATIONAL_ECONOMY_SERVICE"/>
         <xs:enumeration value="LTL_FREIGHT"/>
         <xs:enumeration value="PACKAGE"/>
         <xs:enumeration value="SHIPMENT"/>
         <xs:enumeration value="SHIPMENT_FIVE_POUND_OPTIONAL"/>
         <xs:enumeration value="SHIPMENT_OPTIONAL"/>
         <xs:enumeration value="SPECIAL"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:complexType name="CurrencyExchangeRate">
       <xs:annotation>
         <xs:documentation>Specifies the currency exchange performed on financial amounts for this rate.</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element minOccurs="0" name="FromCurrency" type="xs:string">
           <xs:annotation>
             <xs:documentation>The currency code for the original (converted FROM) currency.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="IntoCurrency" type="xs:string">
           <xs:annotation>
             <xs:documentation>The currency code for the final (converted INTO) currency.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="Rate" type="xs:decimal">
           <xs:annotation>
             <xs:documentation>Multiplier used to convert fromCurrency units to intoCurrency units.</xs:documentation>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:simpleType name="SpecialRatingAppliedType">
       <xs:annotation>
         <xs:documentation>Indicates which special rating cases applied to this shipment.</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="FIXED_FUEL_SURCHARGE"/>
         <xs:enumeration value="IMPORT_PRICING"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:complexType name="Surcharge">
       <xs:annotation>
         <xs:documentation>Identifies each surcharge applied to the shipment.</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element minOccurs="0" name="SurchargeType" type="ns:SurchargeType">
           <xs:annotation>
             <xs:documentation>The type of surcharge applied to the shipment.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="Level" type="ns:SurchargeLevelType">
           <xs:annotation>
             <xs:documentation>??</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="Description" type="xs:string">
           <xs:annotation>
             <xs:documentation>??</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="Amount" type="ns:Money">
           <xs:annotation>
             <xs:documentation>The amount of the surcharge applied to the shipment.</xs:documentation>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:simpleType name="SurchargeType">
       <xs:annotation>
         <xs:documentation>The type of the surcharge.</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="ADDITIONAL_HANDLING"/>
         <xs:enumeration value="APPOINTMENT_DELIVERY"/>
         <xs:enumeration value="BROKER_SELECT_OPTION"/>
         <xs:enumeration value="CANADIAN_DESTINATION"/>
         <xs:enumeration value="CLEARANCE_ENTRY_FEE"/>
         <xs:enumeration value="COD"/>
         <xs:enumeration value="DANGEROUS_GOODS"/>
         <xs:enumeration value="DELIVERY_AREA"/>
         <xs:enumeration value="DELIVERY_SIGNATURE_OPTIONS"/>
         <xs:enumeration value="EMAIL_LABEL"/>
         <xs:enumeration value="EUROPE_FIRST"/>
         <xs:enumeration value="EXPORT"/>
         <xs:enumeration value="FEDEX_TAG"/>
         <xs:enumeration value="FUEL"/>
         <xs:enumeration value="HOME_DELIVERY_APPOINTMENT"/>
         <xs:enumeration value="HOME_DELIVERY_DATE_CERTAIN"/>
         <xs:enumeration value="HOME_DELIVERY_EVENING"/>
         <xs:enumeration value="INSIDE_DELIVERY"/>
         <xs:enumeration value="INSIDE_PICKUP"/>
         <xs:enumeration value="INSURED_VALUE"/>
         <xs:enumeration value="INTERHAWAII"/>
         <xs:enumeration value="NON_STANDARD_CONTAINER"/>
         <xs:enumeration value="OFFSHORE"/>
         <xs:enumeration value="ON_CALL_PICKUP"/>
         <xs:enumeration value="OTHER"/>
         <xs:enumeration value="OUT_OF_DELIVERY_AREA"/>
         <xs:enumeration value="OUT_OF_PICKUP_AREA"/>
         <xs:enumeration value="OVERSIZE"/>
         <xs:enumeration value="PRIORITY_ALERT"/>
         <xs:enumeration value="RESIDENTIAL_DELIVERY"/>
         <xs:enumeration value="RESIDENTIAL_PICKUP"/>
         <xs:enumeration value="RETURN_LABEL"/>
         <xs:enumeration value="SATURDAY_DELIVERY"/>
         <xs:enumeration value="SATURDAY_PICKUP"/>
         <xs:enumeration value="SIGNATURE_OPTION"/>
         <xs:enumeration value="THIRD_PARTY_CONSIGNEE"/>
         <xs:enumeration value="TRANSMART_SERVICE_FEE"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:simpleType name="SurchargeLevelType">
       <xs:annotation>
         <xs:documentation>The type of the surcharge level.</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="PACKAGE"/>
         <xs:enumeration value="SHIPMENT"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:complexType name="Tax">
       <xs:annotation>
         <xs:documentation>Identifies each tax applied to the shipment.</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element minOccurs="0" name="TaxType" type="ns:TaxType">
           <xs:annotation>
             <xs:documentation>The type of tax applied to the shipment.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="Description" type="xs:string">
           <xs:annotation>
             <xs:documentation>??</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="Amount" type="ns:Money">
           <xs:annotation>
             <xs:documentation>The amount of the tax applied to the shipment.</xs:documentation>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:simpleType name="TaxType">
       <xs:annotation>
         <xs:documentation>The type of the tax.</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="EXPORT"/>
         <xs:enumeration value="GST"/>
         <xs:enumeration value="HST"/>
         <xs:enumeration value="OTHER"/>
         <xs:enumeration value="PST"/>
         <xs:enumeration value="VAT"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:complexType name="RateDiscount">
       <xs:annotation>
         <xs:documentation>Identifies a discount applied to the shipment.</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element minOccurs="0" name="RateDiscountType" type="ns:RateDiscountType">
           <xs:annotation>
             <xs:documentation>Identifies the type of discount applied to the shipment.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="Description" type="xs:string">
           <xs:annotation>
             <xs:documentation>??</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="Amount" type="ns:Money">
           <xs:annotation>
             <xs:documentation>The amount of the discount applied to the shipment.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="Percent" type="xs:decimal">
           <xs:annotation>
             <xs:documentation>The percentage of the discount applied to the shipment.</xs:documentation>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:simpleType name="RateDiscountType">
       <xs:annotation>
         <xs:documentation>Identifies the type of discount applied to the shipment.</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="BONUS"/>
         <xs:enumeration value="EARNED"/>
         <xs:enumeration value="OTHER"/>
         <xs:enumeration value="VOLUME"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:complexType name="Rebate">
       <xs:annotation>
         <xs:documentation>Identifies a discount applied to the shipment.</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element minOccurs="0" name="RebateType" type="ns:RebateType">
           <xs:annotation>
             <xs:documentation>??</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="Description" type="xs:string">
           <xs:annotation>
             <xs:documentation>??</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="Amount" type="ns:Money">
           <xs:annotation>
             <xs:documentation>The amount of the discount applied to the shipment.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="Percent" type="xs:decimal">
           <xs:annotation>
             <xs:documentation>The percentage of the discount applied to the shipment.</xs:documentation>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:simpleType name="RebateType">
       <xs:annotation>
         <xs:documentation>Identifies the type of discount applied to the shipment.</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="BASE"/>
         <xs:enumeration value="EARNED"/>
         <xs:enumeration value="GRACE"/>
         <xs:enumeration value="MATRIX"/>
         <xs:enumeration value="OTHER"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:complexType name="RatedPackageDetail">
       <xs:annotation>
         <xs:documentation>If requesting rates using the PackageDetails element (one package at a time) in the request, the rates for each package will be returned in this element. Currently total piece total weight rates are also retuned in this element.</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element minOccurs="0" name="TrackingId" type="ns:TrackingId">
           <xs:annotation>
             <xs:documentation/>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="EffectiveNetDiscount" type="ns:Money">
           <xs:annotation>
             <xs:documentation/>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="AdjustedCodCollectionAmount" type="ns:Money">
           <xs:annotation>
             <xs:documentation>Ground COD is package level.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="OversizeClass" type="ns:OversizeClassType">
           <xs:annotation>
             <xs:documentation/>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="PackageRateDetail" type="ns:PackageRateDetail">
           <xs:annotation>
             <xs:documentation/>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:simpleType name="RatedWeightMethod">
       <xs:annotation>
         <xs:documentation>The method used to calculate the weight to be used in rating the package..</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="ACTUAL"/>
         <xs:enumeration value="DIM"/>
         <xs:enumeration value="FREIGHT_MINIMUM"/>
         <xs:enumeration value="OVERSIZE_1"/>
         <xs:enumeration value="OVERSIZE_2"/>
         <xs:enumeration value="OVERSIZE_3"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:simpleType name="MinimumChargeType">
       <xs:annotation>
         <xs:documentation>Internal FedEx use only.</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="CUSTOMER"/>
         <xs:enumeration value="CUSTOMER_FREIGHT_WEIGHT"/>
         <xs:enumeration value="EARNED_DISCOUNT"/>
         <xs:enumeration value="RATE_SCALE"/>
       </xs:restriction>
     </xs:simpleType>
     <xs:complexType name="VariableHandlingCharges">
       <xs:annotation>
         <xs:documentation>The variable handling charges calculated based on the type variable handling charges requested.</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element minOccurs="0" name="VariableHandlingCharge" type="ns:Money">
           <xs:annotation>
             <xs:documentation>The variable handling charge amount calculated based on the requested variable handling charge detail.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="TotalCustomerCharge" type="ns:Money">
           <xs:annotation>
             <xs:documentation>The calculated varibale handling charge plus the net charge.</xs:documentation>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
     <xs:complexType name="PackageRateDetail">
       <xs:sequence>
         <xs:element name="RateType" type="ns:ReturnedRateType" minOccurs="0">
           <xs:annotation>
             <xs:documentation>The type of rates this information contains either account based or list rates.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="RatedWeightMethod" type="ns:RatedWeightMethod" minOccurs="0">
           <xs:annotation>
             <xs:documentation>The method used to calculate the weight to be used in rating the package..</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="MinimumChargeType" type="ns:MinimumChargeType" minOccurs="0">
           <xs:annotation>
             <xs:documentation>Internal FedEx use only.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="BillingWeight" type="ns:Weight" minOccurs="0">
           <xs:annotation>
             <xs:documentation>The weight that was used to calculate the rate.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="DimWeight" type="ns:Weight" minOccurs="0">
           <xs:annotation>
             <xs:documentation>The dimensional weight that was calculated for this package.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="OversizeWeight" type="ns:Weight" minOccurs="0">
           <xs:annotation>
             <xs:documentation>The oversize weight that was used in the rate calculation.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="BaseCharge" type="ns:Money" minOccurs="0">
           <xs:annotation>
             <xs:documentation>The freight charge that was calculated for this package before surcharges, discounts and taxes..</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="TotalFreightDiscounts" type="ns:Money" minOccurs="0">
           <xs:annotation>
             <xs:documentation>The total discounts used in the rate calculation.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="NetFreight" type="ns:Money" minOccurs="0">
           <xs:annotation>
             <xs:documentation>The base charge minus discounts. </xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="TotalSurcharges" type="ns:Money" minOccurs="0">
           <xs:annotation>
             <xs:documentation>The total amount of all surcharges applied to this package.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="NetFedExCharge" type="ns:Money" minOccurs="0">
           <xs:annotation>
             <xs:documentation>??</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="TotalTaxes" type="ns:Money" minOccurs="0">
           <xs:annotation>
             <xs:documentation>The total amount of all taxes applied to this package.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="NetCharge" type="ns:Money" minOccurs="0">
           <xs:annotation>
             <xs:documentation>The the charge for this package including surcharges, discounts and taxes.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="TotalRebates" type="ns:Money" minOccurs="0">
           <xs:annotation>
             <xs:documentation>The total sum of all rebates applied to this package.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="FreightDiscounts" type="ns:RateDiscount" minOccurs="0" maxOccurs="unbounded">
           <xs:annotation>
             <xs:documentation>A list of discounts that were applied to this package.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="Rebates" type="ns:Rebate" minOccurs="0" maxOccurs="unbounded">
           <xs:annotation>
             <xs:documentation>A list of the surcharges applied to this package.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="Surcharges" type="ns:Surcharge" minOccurs="0" maxOccurs="unbounded">
           <xs:annotation>
             <xs:documentation>A list of the surcharges applied to this package.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="Taxes" type="ns:Tax" minOccurs="0" maxOccurs="unbounded">
           <xs:annotation>
             <xs:documentation>A list of the taxes applied to this package.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element name="VariableHandlingCharges" type="ns:VariableHandlingCharges" minOccurs="0">
           <xs:annotation>
             <xs:documentation>The variable handling charges calculated based on the type variable handling charges requested.</xs:documentation>
           </xs:annotation>
         </xs:element>
       </xs:sequence>
     </xs:complexType>
    <xs:complexType name="PendingShipmentDetail">
       <xs:annotation>
         <xs:documentation>Details used while creating a pending shipment</xs:documentation>
       </xs:annotation>
       <xs:sequence>
         <xs:element name="Type" type="ns:PendingShipmentType">
           <xs:annotation>
             <xs:documentation>Pending Shipment Type</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="ExpirationDate" type="xs:dateTime">
           <xs:annotation>
             <xs:documentation>Date and time when this pending shipment expires.</xs:documentation>
           </xs:annotation>
         </xs:element>
         <xs:element minOccurs="0" name="EmailLabelDetail" type="ns:EMailLabelDetail">
           <xs:annotation>
             <xs:documentation>Details used for emailing a label.</xs:documentation>
           </xs:annotation>
         </xs:element>          
       </xs:sequence>
     </xs:complexType>
    <xs:simpleType name="PendingShipmentType">
       <xs:annotation>
         <xs:documentation>Pending shipment type</xs:documentation>
       </xs:annotation>
       <xs:restriction base="xs:string">
         <xs:enumeration value="EMAIL"/>
       </xs:restriction>
    </xs:simpleType>
   </xs:schema>
 </types>
 <message name="RateRequest">
   <part name="RateRequest" element="ns:RateRequest"/>
 </message>
 <message name="RateReply">
   <part name="RateReply" element="ns:RateReply"/>
 </message>
 <portType name="RatePortType">
   <operation name="getRates" parameterOrder="RateRequest">
     <input message="ns:RateRequest"/>
     <output message="ns:RateReply"/>
   </operation>
 </portType>
 <binding name="RateServiceSoapBinding" type="ns:RatePortType">
   <s1:binding style="document" transport="http://schemas.xmlsoap.org/soap/http"/>
   <operation name="getRates">
     <s1:operation soapAction="getRates" style="document"/>
     <input>
       <s1:body use="literal"/>
     </input>
     <output>
       <s1:body use="literal"/>
     </output>
   </operation>
 </binding>
 <service name="RateService">
   <port name="RateServicePort" binding="ns:RateServiceSoapBinding">
     <s1:address location="'.($this->test?$this->test_url:$this->url).'"/>
   </port>
 </service>
</definitions>
';
		header("Content-type: text/xml");
		header("Content-length: ".strlen($contents));
		echo $contents;
		exit();
	}
	
}
?>