<?php
declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\Core;

/**
 * EAIsConsole Class
 *
 */
 
class EAIsConsole
{
	private $consoleCheckResult;
	
	/**
	 * Checks if STDIN is Defined
	 *
	 * @param array $configArray
	 * @return array
	 */
	public function checkSTDIN()
	{
            if(defined('STDIN') ){

                    //echo("Running from CLI Console");
                    $this->consoleCheckResult = "Console";

            }else{

                    //echo("Not Running from CLI");
                    $this->consoleCheckResult = "Web";

            }

            return $this->consoleCheckResult;
	}
			
}