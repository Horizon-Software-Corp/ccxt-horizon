<?php

namespace ccxt\pro;

// PLEASE DO NOT EDIT THIS FILE, IT IS GENERATED AND WILL BE OVERWRITTEN:
// https://github.com/ccxt/ccxt/blob/master/CONTRIBUTING.md#how-to-contribute-code

use Exception; // a common import
use \React\Async;
use \React\Promise\PromiseInterface;

class tradeogre extends \ccxt\async\tradeogre {

    public function describe(): mixed {
        return $this->deep_extend(parent::describe(), array(
            'has' => array(
                'ws' => true,
                'watchTrades' => true,
                'watchTradesForSymbols' => true,
                'watchOrderBook' => true,
                'watchOrderBookForSymbols' => false,
                'watchOHLCV' => false,
                'watchOHLCVForSymbols' => false,
                'watchOrders' => false,
                'watchMyTrades' => false,
                'watchTicker' => false,
                'watchTickers' => false,
                'watchBidsAsks' => false,
                'watchBalance' => false,
                'createOrderWs' => false,
                'editOrderWs' => false,
                'cancelOrderWs' => false,
                'cancelOrdersWs' => false,
            ),
            'urls' => array(
                'api' => array(
                    'ws' => 'wss://tradeogre.com:8443',
                ),
            ),
            'options' => array(
            ),
            'streaming' => array(
            ),
        ));
    }

    public function watch_order_book(string $symbol, ?int $limit = null, $params = array ()): PromiseInterface {
        return Async\async(function () use ($symbol, $limit, $params) {
            /**
             * watches information on open orders with bid (buy) and ask (sell) prices, volumes and other data
             *
             * @see https://tradeogre.com/help/api
             *
             * @param {string} $symbol unified $symbol of the $market to fetch the order book for
             * @param {int} [$limit] the maximum amount of order book entries to return (not used by the exchange)
             * @param {array} [$params] extra parameters specific to the exchange API endpoint
             * @return {array} A dictionary of ~@link https://docs.ccxt.com/#/?id=order-book-structure order book structures~ indexed by $market symbols
             */
            Async\await($this->load_markets());
            $market = $this->market($symbol);
            $url = $this->urls['api']['ws'];
            $messageHash = 'orderbook' . ':' . $market['symbol'];
            $request = array(
                'a' => 'subscribe',
                'e' => 'book',
                't' => $market['id'],
            );
            $orderbook = Async\await($this->watch($url, $messageHash, $this->extend($request, $params), $messageHash));
            return $orderbook->limit ();
        }) ();
    }

    public function handle_order_book(Client $client, $message) {
        //
        // initial snapshot is fetched with ccxt's fetchOrderBook
        // the feed does not include a snapshot, just the deltas
        //
        //     {
        //         "e" => "book",
        //         "t" => "ETH-USDT",
        //         "s" => "10752324",
        //         "d" => {
        //             "bids" => array( "1787.02497915" => "0" ),
        //             "asks" => array()
        //         }
        //     }
        //
        $marketId = $this->safe_string($message, 't');
        $symbol = $this->safe_symbol($marketId);
        if (!(is_array($this->orderbooks) && array_key_exists($symbol, $this->orderbooks))) {
            $this->orderbooks[$symbol] = $this->order_book(array());
        }
        $storedOrderBook = $this->orderbooks[$symbol];
        $nonce = $this->safe_integer($storedOrderBook, 'nonce');
        $deltaNonce = $this->safe_integer($message, 's');
        $messageHash = 'orderbook:' . $symbol;
        if ($nonce === null) {
            $cacheLength = count($storedOrderBook->cache);
            $snapshotDelay = $this->handle_option('watchOrderBook', 'snapshotDelay', 6);
            if ($cacheLength === $snapshotDelay) {
                $this->spawn(array($this, 'load_order_book'), $client, $messageHash, $symbol, null, array());
            }
            $storedOrderBook->cache[] = $message;
            return;
        } elseif ($nonce >= $deltaNonce) {
            return;
        }
        $this->handle_delta($storedOrderBook, $message);
        $client->resolve ($storedOrderBook, $messageHash);
    }

    public function handle_delta($orderbook, $delta) {
        // $timestamp = $this->milliseconds(); // todo check if this is correct
        // $orderbook['timestamp'] = $timestamp;
        // $orderbook['datetime'] = $this->iso8601($timestamp);
        $orderbook['nonce'] = $this->safe_integer($delta, 's');
        $data = $this->safe_dict($delta, 'd', array());
        $bids = $this->safe_dict($data, 'bids', array());
        $asks = $this->safe_dict($data, 'asks', array());
        $storedBids = $orderbook['bids'];
        $storedAsks = $orderbook['asks'];
        $this->handle_bid_asks($storedBids, $bids);
        $this->handle_bid_asks($storedAsks, $asks);
    }

    public function handle_bid_asks($bookSide, $bidAsks) {
        $keys = is_array($bidAsks) ? array_keys($bidAsks) : array();
        for ($i = 0; $i < count($keys); $i++) {
            $price = $this->safe_string($keys, $i);
            $amount = $this->safe_number($bidAsks, $price);
            $bidAsk = array( $this->parse_number($price), $amount );
            $bookSide->storeArray ($bidAsk);
        // for ($i = 0; $i < count($bidAsks); $i++) {
        //     $bidAsk = $this->parse_bid_ask($bidAsks[$i]);
        //     $bookSide->storeArray ($bidAsk);
        // }
        }
    }

    public function get_cache_index($orderbook, $deltas) {
        $firstElement = $deltas[0];
        $firstElementNonce = $this->safe_integer($firstElement, 's');
        $nonce = $this->safe_integer($orderbook, 'nonce');
        if ($nonce < $firstElementNonce) {
            return -1;
        }
        for ($i = 0; $i < count($deltas); $i++) {
            $delta = $deltas[$i];
            $deltaNonce = $this->safe_integer($delta, 's');
            if ($deltaNonce === $nonce) {
                return $i + 1;
            }
        }
        return count($deltas);
    }

    public function watch_trades(string $symbol, ?int $since = null, ?int $limit = null, $params = array ()): PromiseInterface {
        return Async\async(function () use ($symbol, $since, $limit, $params) {
            /**
             * watches information on multiple trades made in a $market
             *
             * @see https://tradeogre.com/help/api
             *
             * @param {string} $symbol unified $market $symbol of the $market trades were made in
             * @param {int} [$since] the earliest time in ms to fetch trades for
             * @param {int} [$limit] the maximum number of trade structures to retrieve
             * @param {array} [$params] extra parameters specific to the exchange API endpoint
             * @return {array[]} a list of ~@link https://docs.ccxt.com/#/?id=trade-structure trade structures~
             */
            Async\await($this->load_markets());
            $market = $this->market($symbol);
            $symbol = $market['symbol'];
            return Async\await($this->watch_trades_for_symbols(array( $symbol ), $since, $limit, $params));
        }) ();
    }

    public function watch_trades_for_symbols(array $symbols, ?int $since = null, ?int $limit = null, $params = array ()): PromiseInterface {
        return Async\async(function () use ($symbols, $since, $limit, $params) {
            /**
             *
             * @see https://tradeogre.com/help/api
             *
             * get the list of most recent $trades for a list of $symbols
             * @param {string[]} $symbols unified $symbol of the market to fetch $trades for (empty array means all markets)
             * @param {int} [$since] timestamp in ms of the earliest trade to fetch
             * @param {int} [$limit] the maximum amount of $trades to fetch
             * @param {array} [$params] extra parameters specific to the exchange API endpoint
             * @return {array[]} a list of ~@link https://docs.ccxt.com/#/?id=public-$trades trade structures~
             */
            Async\await($this->load_markets());
            $symbols = $this->market_symbols($symbols, null, true);
            $messageHashes = array();
            $symbolsLength = 0;
            if ($symbols !== null) {
                $symbolsLength = count($symbols);
            }
            if ($symbolsLength > 0) {
                for ($i = 0; $i < count($symbols); $i++) {
                    $symbol = $symbols[$i];
                    $messageHash = 'trades:' . $symbol;
                    $messageHashes[] = $messageHash;
                }
            } else {
                $messageHash = 'trades';
                $messageHashes[] = $messageHash;
            }
            $request = array(
                'a' => 'subscribe',
                'e' => 'trade',
                't' => '*',
            );
            $url = $this->urls['api']['ws'];
            $trades = Async\await($this->watch_multiple($url, $messageHashes, $this->extend($request, $params), array( 'trades' )));
            if ($this->newUpdates) {
                $first = $this->safe_dict($trades, 0);
                $tradeSymbol = $this->safe_string($first, 'symbol');
                $limit = $trades->getLimit ($tradeSymbol, $limit);
            }
            return $this->filter_by_since_limit($trades, $since, $limit, 'timestamp', true);
        }) ();
    }

    public function handle_trade(Client $client, $message) {
        //
        //     {
        //         "e" => "trade",
        //         "t" => "LTC-USDT",
        //         "d" => {
        //             "t" => 0,
        //             "p" => "84.50000000",
        //             "q" => "1.28471270",
        //             "d" => "1745392002"
        //         }
        //     }
        //
        $marketId = $this->safe_string($message, 't');
        $market = $this->safe_market($marketId);
        $data = $this->safe_dict($message, 'd', array());
        $symbol = $market['symbol'];
        if (!(is_array($this->trades) && array_key_exists($symbol, $this->trades))) {
            $limit = $this->safe_integer($this->options, 'tradesLimit', 1000);
            $stored = new ArrayCache ($limit);
            $this->trades[$symbol] = $stored;
        }
        $cache = $this->trades[$symbol];
        $trade = $this->parse_ws_trade($data, $market);
        $cache->append ($trade);
        $messageHash = 'trades:' . $symbol;
        $client->resolve ($cache, $messageHash);
        $client->resolve ($cache, 'trades');
    }

    public function parse_ws_trade($trade, $market = null) {
        //
        //     {
        //         "t" => 0,
        //         "p" => "84.50000000",
        //         "q" => "1.28471270",
        //         "d" => "1745392002"
        //     }
        //
        $timestamp = $this->safe_integer_product($trade, 'd', 1000);
        $sideEnum = $this->safe_string($trade, 't');
        return $this->safe_trade(array(
            'info' => $trade,
            'id' => null,
            'timestamp' => $timestamp,
            'datetime' => $this->iso8601($timestamp),
            'symbol' => $this->safe_string($market, 'symbol'),
            'order' => null,
            'type' => null,
            'side' => $this->parse_ws_trade_side($sideEnum),
            'takerOrMaker' => null,
            'price' => $this->safe_string($trade, 'p'),
            'amount' => $this->safe_string($trade, 'q'),
            'cost' => null,
            'fee' => array(
                'currency' => null,
                'cost' => null,
            ),
        ), $market);
    }

    public function parse_ws_trade_side($side) {
        $sides = array(
            '0' => 'buy',
            '1' => 'sell',
        );
        return $this->safe_string($sides, $side, $side);
    }

    public function handle_message(Client $client, $message) {
        $methods = array(
            'book' => array($this, 'handle_order_book'),
            'trade' => array($this, 'handle_trade'),
        );
        $event = $this->safe_string($message, 'e');
        $method = $this->safe_value($methods, $event);
        if ($method !== null) {
            $method($client, $message);
        }
    }
}
