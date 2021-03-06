<?php

namespace Kmedia\ReCaptcha;

use Locale;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\FormField;
use SilverStripe\i18n\i18n;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\View\Requirements;

class ReCaptchaField extends FormField
{
    /**
     * Captcha theme, currently options are light and dark
     * @config ReCaptchaField.theme
     * @default light
     * @var string
     */
    private static $theme = 'light';
    /**
     * Captcha size, currently options are normal, compact and invisible
     * @config ReCaptchaField.size
     * @default normal
     * @var string
     */
    private static $size = 'normal';
    /**
     * Captcha badge, currently options are bottomright, bottomleft and inline
     * @config ReCaptchaField.size
     * @default bottomright
     * @var string
     */
    private static $badge = 'bottomright';
    /**
     * Recaptcha Site Key - Configurable via Injector config
     */
    protected $siteKey;
    /**
     * Recaptcha Secret Key - Configurable via Injector config
     */
    protected $secretKey;

    /**
     * Getter for siteKey
     * @return string
     */
    public function getSiteKey()
    {
        return $this->siteKey;
    }

    /**
     * Setter for siteKey to allow injector config to override the value
     */
    public function setSiteKey($siteKey)
    {
        $this->siteKey = $siteKey;
    }

    /**
     * Getter for secretKey
     * @return string
     */
    public function getSecretKey()
    {
        return $this->secretKey;
    }

    /**
     * Setter for secretKey to allow injector config to override the value
     * @param string $secretKey
     */
    public function setSecretKey($secretKey)
    {
        $this->secretKey = $secretKey;
    }

    /**
     * Getter for theme
     * @return string
     */
    public function getTheme()
    {
        return $this->config()->theme;
    }

    /**
     * Getter for size
     * @return string
     */
    public function getSize()
    {
        return $this->config()->size;
    }

    /**
     * Getter for badge
     * @return string
     */
    public function getBadge()
    {
        return $this->config()->badge;
    }

    /**
     * Adds the requirements and returns the form field.
     * @param array $properties
     * @return DBHTMLText
     */
    public function Field($properties = array())
    {
        if (empty($this->siteKey) || empty($this->secretKey)) {
            user_error('You must set SS_RECAPTCHA_SITE_KEY and SS_RECAPTCHA_SECRET_KEY environment.', E_USER_ERROR);
        }

        Requirements::customScript("var SS_LOCALE='" . Locale::getPrimaryLanguage(i18n::get_locale()) . "',ReCaptchaFormId='" . $this->getFormID() . "';");
        Requirements::javascript('kmedia/silverstripe-recaptcha:javascript/domReady.js');
        Requirements::javascript('kmedia/silverstripe-recaptcha:javascript/ReCaptchaField.js');

        return parent::Field($properties);
    }

    /**
     * Getter for the form's id
     * @return string
     */
    public function getFormID()
    {
        return $this->form ? $this->getTemplateHelper()->generateFormID($this->form) : null;
    }

    public function validate($validator)
    {
        $recaptchaResponse = Controller::curr()->getRequest()->requestVar('g-recaptcha-response');
        $response = json_decode((string)$this->siteVerify($recaptchaResponse), true);

        return $this->verify($response, $validator);
    }

    private function siteVerify($token)
    {
        $url = 'https://www.google.com/recaptcha/api/siteverify?secret='
            . $this->secretKey . '&response=' . rawurlencode($token)
            . '&remoteip=' . rawurlencode($_SERVER['REMOTE_ADDR']);

        $ch = curl_init();

        if ($ch === false) {
            user_error('An error occurred when initializing cURL.', E_USER_ERROR);
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $result = curl_exec($ch);

        if ($result === false) {
            user_error('An error occurred while cURL was being executed: ' . curl_error($ch), E_USER_ERROR);
            return false;
        }

        curl_close($ch);
        return $result;
    }

    private function verify($response, $validator)
    {
        if (is_array($response)) {
            if (array_key_exists('success', $response) && $response['success'] == false) {
                $validator->validationError(
                    $this->name,
                    _t('Kmedia\\ReCaptcha.EMPTY',
                        'Please answer the captcha, if you do not see the captcha please enable JavaScript.'),
                    'validation'
                );
                return false;
            }
        } else {
            $validator->validationError($this->name,
                _t('Kmedia\\ReCaptcha.VALIDATE_ERROR', 'Captcha could not be validated.'),
                'validation');
            return false;
        }
        return true;
    }
}
