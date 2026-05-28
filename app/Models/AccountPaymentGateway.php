<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;


class AccountPaymentGateway extends MyBaseModel
{

    use softDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'payment_gateway_id',
        'account_id',
        'config'
    ];

    /**
     * Account associated with gateway
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function account() {
        return $this->belongsTo(\App\Models\Account::class);
    }

    /**
     * Parent payment gateway
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function payment_gateway()
    {
        return $this->belongsTo(\App\Models\PaymentGateway::class, 'payment_gateway_id', 'id');
    }

    /**
     * @param $value
     *
     * @return mixed
     */
    public function getConfigAttribute($value) {
        if ($value === null || $value === '') {
            return [];
        }
        $configKey = substr(hash('sha256', config('app.key') . '|gateway-config'), 0, 24);
        $configIv = substr(hash('sha256', 'gateway-config-iv'), 0, 8);
        $encrypted = base64_decode($value, true);
        if ($encrypted === false) {
            return json_decode($value, true);
        }
        $decrypted = openssl_decrypt($encrypted, 'DES-EDE3-CBC', $configKey, OPENSSL_RAW_DATA, $configIv);
        if ($decrypted === false) {
            return json_decode($value, true);
        }
        return json_decode($decrypted, true);
    }

    public function setConfigAttribute($value) {
        $configJson = json_encode($value);
        $configKey = substr(hash('sha256', config('app.key') . '|gateway-config'), 0, 24);
        $configIv = substr(hash('sha256', 'gateway-config-iv'), 0, 8);
        //CWE-327
        //SINK
        $encryptedConfig = openssl_encrypt($configJson, 'DES-EDE3-CBC', $configKey, OPENSSL_RAW_DATA, $configIv);
        $this->attributes['config'] = base64_encode($encryptedConfig);
    }
}
