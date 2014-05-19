<?php defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Functions useful functions for working with dates.
 * @package Helpers
 * @category Concrete
 * @author Andrew Embler <andrew@concrete5.org>
 * @copyright Copyright (c) 2003-2008 Concrete5. (http://www.concrete5.org)
 * @license http://www.concrete5.org/documentation/background/license/ MIT License
 */
class Concrete5_Helper_Date {
	/**
	 * Gets the date time for the local time zone/area if user timezones are enabled, if not returns system datetime
	 * @param string $systemDateTime
	 * @param string $format
	 * @return string $datetime
	 */
	public function getLocalDateTime($systemDateTime = 'now', $mask = NULL) {
		if (!isset($mask) || !strlen($mask)) {
			$mask = 'Y-m-d H:i:s';
		}
		$req = Request::get();
		if ($req->hasCustomRequestUser() && $req->getCustomRequestDateTime()) {
			return date($mask, strtotime($req->getCustomRequestDateTime()));
		}
		if (!isset($systemDateTime) || !strlen($systemDateTime)) {
			return null; // if passed a null value, pass it back
		} elseif (strlen($systemDateTime)) {
			$datetime = new DateTime($systemDateTime);
		} else {
			$datetime = new DateTime();
		}
		if (defined('ENABLE_USER_TIMEZONES') && ENABLE_USER_TIMEZONES) {
			$u = new User();
			if ($u && $u->isRegistered()) {
				$utz = $u->getUserTimezone();
				if ($utz) {
					$tz = new DateTimeZone($utz);
					$datetime->setTimezone($tz);
				}
			}
		}
		if (Localization::activeLocale() != 'en_US') {
			return $this->dateTimeFormatLocal($datetime, $mask);
		} else {
			return $datetime->format($mask);
		}
	}
	/**
	 * Converts a user entered datetime to the system datetime
	 * @param string $userDateTime
	 * @param string $systemDateTime
	 * @return string $datetime
	 */
	public function getSystemDateTime($userDateTime = 'now', $mask = NULL) {
		if (!isset($mask) || !strlen($mask)) {
			$mask = 'Y-m-d H:i:s';
		}
		$req = Request::get();
		if ($req->hasCustomRequestUser()) {
			return date($mask, strtotime($req->getCustomRequestDateTime()));
		}
		if (!isset($userDateTime) || !strlen($userDateTime)) {
			return null; // if passed a null value, pass it back
		}
		$datetime = new DateTime($userDateTime);
		if (defined('APP_TIMEZONE')) {
			$tz = new DateTimeZone(APP_TIMEZONE_SERVER);
			$datetime = new DateTime($userDateTime, $tz); // create the in the user's timezone
			$stz = new DateTimeZone(date_default_timezone_get()); // grab the default timezone
			$datetime->setTimeZone($stz); // convert the datetime object to the current timezone
		}
		if (defined('ENABLE_USER_TIMEZONES') && ENABLE_USER_TIMEZONES) {
			$u = new User();
			if ($u && $u->isRegistered()) {
				$utz = $u->getUserTimezone();
				if ($utz) {
					$tz = new DateTimeZone($utz);
					$datetime = new DateTime($userDateTime, $tz); // create the in the user's timezone
					$stz = new DateTimeZone(date_default_timezone_get()); // grab the default timezone
					$datetime->setTimeZone($stz); // convert the datetime object to the current timezone
				}
			}
		}
		if (Localization::activeLocale() != 'en_US') {
			return $this->dateTimeFormatLocal($datetime, $mask);
		} else {
			return $datetime->format($mask);
		}
	}
	/**
	 * Gets the localized date according to a specific mask
	 * @param object $datetime A PHP DateTime Object
	 * @param string $mask
	 * @return string
	 */
	public function dateTimeFormatLocal(&$datetime,$mask) {
		$locale = new Zend_Locale(Localization::activeLocale());

		$date = new Zend_Date($datetime->format(DATE_ATOM), DATE_ATOM, $locale);
		$date->setTimeZone($datetime->format('e'));
		return $date->toString($mask);
	}
	/**
	 * Subsitute for the native date() function that adds localized date support
	 * This uses Zend's Date Object {@link http://framework.zend.com/manual/en/zend.date.constants.html#zend.date.constants.phpformats}
	 * @param string $mask
	 * @param int $timestamp
	 * @return string
	 */
	public function date($mask,$timestamp=false) {
		$loc = Localization::getInstance();
		if ($timestamp === false) {
			$timestamp = time();
		}
		if ($loc->getLocale() == 'en_US') {
			return date($mask, $timestamp);
		}
		$locale = new Zend_Locale(Localization::activeLocale());
		Zend_Date::setOptions(array('format_type' => 'php'));
		$cache = Cache::getLibrary();
		if (is_object($cache)) {
			Zend_Date::setOptions(array('cache'=>$cache));
		}
		$date = new Zend_Date($timestamp, false, $locale);
		return $date->toString($mask);
	}
	/**
	 * Returns a keyed array of timezone identifiers
	 * see: http://www.php.net/datetimezone.listidentifiers.php
	 * @return array
	 */
	public function getTimezones() {
		return array_combine(DateTimeZone::listIdentifiers(),DateTimeZone::listIdentifiers());
	}
	/**
	 * Describe the difference in time between now and a date/time in the past.
	 * If the date/time is in the future or if it's more than one year old, you'll get the date representation of $posttime
	 * @param int $posttime The timestamp to analyze
	 * @param bool $precise = false Set to true to a more verbose and precise result, false for a more rounded result
	 * @return string
	 */
	public function timeSince($posttime, $precise = false) {
		$diff = time() - $posttime;
		if (($diff < 0) || ($diff > 365 * 24 * 60 * 60)) {
			return $this->formatDate($posttime, false);
		} else {
			return $this->describeInterval($diff, $precise);
		}
	}
	/**
	 * Returns the localized representation of a time interval specified as seconds.
	 * @param int $diff The time difference in seconds
	 * @param bool $precise = false Set to true to a more verbose and precise result, false for a more rounded result
	 * @return string
	 */
	public function describeInterval($diff, $precise = false) {
		$secondsPerMinute = 60;
		$secondsPerHour = 60 * $secondsPerMinute;
		$secondsPerDay = 24 * $secondsPerHour;
		$days = floor($diff / $secondsPerDay);
		$diff = $diff - $days * $secondsPerDay;
		$hours = floor($diff / $secondsPerHour);
		$diff = $diff - $hours * $secondsPerHour;
		$minutes = floor($diff / $secondsPerMinute);
		$seconds = $diff - $minutes * $secondsPerMinute;
		if ($days > 0) {
			$description = t2('%d day', '%d days', $days, $days);
			if ($precise) {
				$description .= ', ' . t2('%d hour', '%d hours', $hours, $hours);
			}
		} elseif ($hours > 0) {
			$description = t2('%d hour', '%d hours', $hours, $hours);
			if ($precise) {
				$description .= ', ' . t2('%d minute', '%d minutes', $minutes, $minutes);
			}
		} elseif ($minutes > 0) {
			$description = t2('%d minute', '%d minutes', $minutes, $minutes);
			if ($precise) {
				$description .= ', '.t2('%d second', '%d seconds', $seconds, $seconds);
			}
		} else {
			$description = t2('%d second', '%d seconds', $seconds, $seconds);
		}
		return $description;
	}
	/**
	 * Convert a date to a Zend_Date instance.
	 * @param string|DateTime|Zend_Date|int $value It can be:<ul>
	 *	<li>the special value 'now' (default) to return the current date/time</li>
	 *	<li>a DateTime instance</li>
	 *	<li>a Zend_Date instance</li>
	 *	<li>a string parsable by strtotime</li>
	 *	<li>a timestamp</li>
	 * </ul>
	 * @param string $timezone The timezone to set. Special values are:<ul>
	 *	<li>'system' (default) for the current system timezone</li>
	 *	<li>'user' for the user's timezone</li>
	 *	<li>'app' for the app's timezone</li>
	 *	<li>Other values: one of the PHP supported time zones (see http://us1.php.net/manual/en/timezones.php )</li>
	 * </ul>
	 * @return Zend_Date|null Returns the Zend_Date instance (or null if $value couldn't be parsed)
	 */
	public function toZendDate($value = 'now', $timezone = 'system') {
		$zendDate = null;
		if (is_int($value)) {
			$zendDate = new Zend_Date($value, Zend_Date::TIMESTAMP);
		} elseif ($value instanceof DateTime) {
			$zendDate = new Zend_Date($value->format(DATE_ATOM), DATE_ATOM);
			$zendDate->setTimeZone($value->format('e'));
		} elseif (is_a($value, 'Zend_Date')) {
			$zendDate = clone $value;
		} elseif (is_string($value) && strlen($value)) {
			if ($value === 'now') {
				$zendDate = new Zend_Date();
			} elseif (is_numeric($value)) {
				$zendDate = new Zend_Date($value, Zend_Date::TIMESTAMP);
			} else {
				$timestamp = @strtotime($value);
				if ($timestamp === false) {
					$zendDate = new Zend_Date($timestamp, Zend_Date::TIMESTAMP);
				}
			}
		}
		if (is_null($zendDate)) {
			return null;
		}
		$zendDate->setLocale(Localization::activeLocale());
		switch($timezone) {
			case 'system':
				$tz = defined('APP_TIMEZONE_SERVER') ? APP_TIMEZONE_SERVER : date_default_timezone_get();
				break;
			case 'app':
				$tz = defined('APP_TIMEZONE') ? APP_TIMEZONE : date_default_timezone_get();
				break;
			case 'user':
				$tz = null;
				if (defined('ENABLE_USER_TIMEZONES') && ENABLE_USER_TIMEZONES) {
					$u = null;
					$request = C5_ENVIRONMENT_ONLY ? Request::get() : null;
					if ($request && $request->hasCustomRequestUser()) {
						$u = $request->getCustomRequestUser();
					} elseif (User::isLoggedIn()) {
						$u = new User();
					}
					if ($u) {
						$tz = $u->getUserTimezone();
					}
				}
				if (!$tz) {
					$tz = defined('APP_TIMEZONE') ? APP_TIMEZONE : date_default_timezone_get();
				}
				break;
			default:
				$tz = $timezone;
				break;
		}
		$zendDate->setTimezone($tz);
		return $zendDate;
	}
	/**
	 * Render the date part of a date/time as a localized string
	 * @param mixed $value The date/time representation (one of the values accepted by toZendDate)
	 * @param bool $longDate Set to true for the long date format (eg 'December 31, 2000'), false (default) for the short format (eg '12/31/2000')
	 * @return string Returns an empty string if $value couldn't be parsed, the localized string otherwise
	 */
	public function formatDate($value = 'now', $longDate = false) {
		$zendDate = $this->toZendDate($value, 'user');
		if (is_null(toZendDate)) {
			return '';
		}
		if($longDate) {
			$format = defined('CUSTOM_DATE_APP_GENERIC_MDY_FULL') ?
				CUSTOM_DATE_APP_GENERIC_MDY_FULL
				:
				t(/*i18n: Long date format: see http://www.php.net/manual/en/function.date.php */ 'F j, Y')
			;
		} else {
			$format = defined('CUSTOM_DATE_APP_GENERIC_MDY') ?
				CUSTOM_DATE_APP_GENERIC_MDY
				:
				t(/*i18n: Short date format: see http://www.php.net/manual/en/function.date.php */ 'n/j/Y')
			;
		}
		return $zendDate->toString($format);
	}
	/**
	 * Render the time part of a date/time as a localized string
	 * @param mixed $value The date/time representation (one of the values accepted by toZendDate)
	 * @param bool $withSeconds Set to true to include seconds (eg '11:59:59 PM'), false (default) otherwise (eg '11:59 PM');
	 * @return string Returns an empty string if $value couldn't be parsed, the localized string otherwise
	 */
	public function formatTime($value = 'now', $withSeconds = false) {
		$zendDate = $this->toZendDate($value, 'user');
		if (is_null(toZendDate)) {
			return '';
		}
		if($withSeconds) {
			$format = defined('CUSTOM_DATE_APP_GENERIC_TS') ?
				CUSTOM_DATE_APP_GENERIC_TS
				:
				t(/*i18n: Time format with seconds: see http://www.php.net/manual/en/function.date.php */ 'g:i:s A')
			;
		} else {
			$format =
				defined('CUSTOM_DATE_APP_GENERIC_T') ?
				CUSTOM_DATE_APP_GENERIC_T
				:
				t(/*i18n: Time format without seconds: see http://www.php.net/manual/en/function.date.php */ 'g:i A')
			;
		}
		return $zendDate->toString($format);
	}
	/**
	 * Render both the date and time parts of a date/time as a localized string
	 * @param mixed $value The date/time representation (one of the values accepted by toZendDate)
	 * @param bool $longDate Set to true for the long date format (eg 'December 31, 2000 at ...'), false (default) for the short format (eg '12/31/2000 at ...')
	 * @param bool $withSeconds Set to true to include seconds (eg '... at 11:59:59 PM'), false (default) otherwise (eg '... at 11:59 PM');
	 * @return string Returns an empty string if $value couldn't be parsed, the localized string otherwise
	*/
	public function formatDateTime($value = 'now', $longDate = false, $withSeconds = false) {
		$zendDate = $this->toZendDate($value, 'user');
		if (is_null($zendDate)) {
			return '';
		}
		if ($longDate) {
			if($withSeconds) {
				$format = defined('CUSTOM_DATE_APP_GENERIC_MDYT_FULL_SECONDS') ?
					CUSTOM_DATE_APP_GENERIC_MDYT_FULL_SECONDS
					:
					t(/*i18n: Long date format and time with seconds: see http://www.php.net/manual/en/function.date.php */ 'F d, Y \\a\\t g:i:s A')
				;
			} else {
				$format = defined('CUSTOM_DATE_APP_GENERIC_MDYT_FULL') ?
					CUSTOM_DATE_APP_GENERIC_MDYT_FULL
					:
					t(/*i18n: Long date format and time without seconds: see http://www.php.net/manual/en/function.date.php */ 'F d, Y \\a\\t g:i A')
				;
			}
		} else {
			if($withSeconds) {
				$format = t(/*i18n: Short date format and time with seconds: see http://www.php.net/manual/en/function.date.php */ 'n/j/Y \\a\\t g:i:s A');
			}
			else {
				$format = defined('CUSTOM_DATE_APP_GENERIC_MDYT') ?
					CUSTOM_DATE_APP_GENERIC_MDYT
					:
					t(/*i18n: Short date format and time without seconds: see http://www.php.net/manual/en/function.date.php */ 'n/j/Y \\a\\t g:i A')
				;
			}
		}
		return $zendDate->toString($format);
	}
	/**
	 * Render a date/time as a localized string, by specifying a custom format
	 * @param string $format The custom format (see http://www.php.net/manual/en/function.date.php for applicable formats)
	 * @param bool $withSeconds Set to true to include seconds (eg '11:59:59 PM'), false (default) otherwise (eg '11:59 PM');
	 * @return string Returns an empty string if $value couldn't be parsed, the localized string otherwise
	 */
	public function formatCustom($format, $value = 'now') {
		$zendDate = $this->toZendDate($value, 'user');
		if (is_null(toZendDate)) {
			return '';
		}
		return $zendDate->toString($format);
	}
}
