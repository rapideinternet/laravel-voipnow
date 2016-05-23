<?php namespace Rapide\VoipNow\Facades;

use Illuminate\Support\Facades\Facade;

class Voip extends Facade {

	protected static function getFacadeAccessor() {
		return '\Rapide\VoipNow\Services\VoipService';
	}

}
