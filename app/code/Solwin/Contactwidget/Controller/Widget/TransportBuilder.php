<?php
/**
 * Solwin Infotech
 * Solwin Contact Form Widget Extension
 *
 * @category   Solwin
 * @package    Solwin_Contactwidget
 * @copyright  Copyright © 2006-2016 Solwin (https://www.solwininfotech.com)
 * @license    https://www.solwininfotech.com/magento-extension-license/ 
 */
namespace Solwin\Contactwidget\Controller\Widget;

use Zend_Mime;

/**
* Class TransportBuilder
* @package Dckap\CustomModule\Model\Mail
*/
class TransportBuilder extends \Magento\Framework\Mail\Template\TransportBuilder
{
   /**
    * @param string $pdfString
    * @param string $filename
    * @return mixed
    */
   public function addAttachment($pdfString, $filename)
   {
       If ($filename == '') {
           $filename="attachment";
       }
       $this->message->createAttachment(
           $pdfString,
           'application/pdf',
           \Zend_Mime::DISPOSITION_ATTACHMENT,
           \Zend_Mime::ENCODING_BASE64,
           $filename.'.pdf'
       );
       return $this;
   }
}