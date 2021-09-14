<?php

namespace p2pool;

use MoneroIntegrations\MoneroPhp\Cryptonote;
use p2pool\db\Miner;

class CoinbaseTransactionOutputs{
    private array $outputs = [];

    /** @var CoinbaseTransactionOutputs[] */
    private static array $cache = [];

    /**
     * @param Miner[] $miners
     */
    public function matchOutputs(array $miners, string $tx_privkey): array {
        $matched = [];
        foreach ($miners as $miner){
            $ma = $miner->getMoneroAddress();
            foreach ($this->outputs as $i => $o){
                if(isset($matched[$i])){
                    continue;
                }
                if($ma->getEphemeralPublicKey($tx_privkey, $o->index) === $o->key){
                    $matched[$miner->getId()] = clone $o;
                }
            }
        }

        return $matched;
    }

    public static function fromTransactionId($txId): ?CoinbaseTransactionOutputs {
        if(isset(static::$cache[$txId])){
            return static::$cache[$txId];
        }else if (file_exists($path = "/cache/tx_{$txId}.json")){
            $outputs = json_decode(file_get_contents($path));
            $o = new CoinbaseTransactionOutputs();
            $o->outputs = $outputs;
            return static::$cache[$txId] = $o;
        }

        $ret = Utils::moneroRPC("get_transactions", [
            "txs_hashes" => [$txId],
            "decode_as_json" => true
        ]);

        if(isset($ret->txs[0]->as_json)){
            $outputs = [];
            $tx = json_decode($ret->txs[0]->as_json);
            foreach ($tx->vout as $i => $out){
                $outputs[$i] = (object) [
                    "amount" => $out->amount,
                    "key" => $out->target->key,
                    "index" => $i,
                    ];
            }

            $o = new CoinbaseTransactionOutputs();
            $o->outputs = $outputs;
            file_put_contents($path, json_encode($o->outputs));
            return static::$cache[$txId] = $o;
        }

        return null;
    }
}