<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Events extends My_Controller
{
    private $_user_listing_headers = 'user_listing_headers';

    public function __construct()
    {
        parent::__construct();
        $this->load->helper('url');
        $this->load->helper('form');
        $this->load->model('User_model');
        $this->load->model('Event_model');
        $this->load->model('Fancy_data_model');

        $this->load->model('Betting_model');
        $this->load->model('User_info_model');
        $this->load->model('Favourite_event_model');
        $this->load->model('Masters_betting_settings_model');
        $this->load->model('Market_book_odds_model');

        $this->load->model('Event_exchange_entry_model');
        $this->load->library('commonlibrary');
        $this->load->library('commonlib');
        $this->load->library('session');
    }


    public function getevents($type = null)
    {
        if ($type == 'inplay') {
            $data['inplay'] = '1';
            $list_events = $this->Event_model->list_events($data);
        } else {
            $data = array();
            $list_events = $this->Event_model->list_events($data);
        }


        $exchangeData = array();
        if (!empty($list_events)) {

            foreach ($list_events as $key => $list_event) {
                $exchangeData[$key] = $list_event;


                $market_book_odds_runner = $this->Event_model->list_market_book_odds_runner($list_event['event_id']);

                $exchangeData[$key]['runners'] = $market_book_odds_runner;
            }
        }
        $dataArray['crickets'] = $exchangeData;
        $matchListingHtml = $this->load->viewPartial('dashboardMatchListing', $dataArray);
        echo json_encode(array('matchListingHtml' => $matchListingHtml));
    }






    public function backlays()
    {
        $event_id = $this->input->post('matchId');
        $market_id = $this->input->post('MarketId');

        $data['event_id'] = $event_id;
        $list_events = $this->Event_model->list_events($data);


        $exchangeData = array();
        $fantacyData = array();
        if (get_user_type() == 'User') {

            /*************** Type = 1 is Market and event */
            $block_markets = get_users_block_markets(array('user_id' => get_user_id(), 'type' => '1'));
        }

        if (!empty($list_events)) {

            foreach ($list_events as $key => $list_event) {
                if (get_user_type() == 'User') {
                    if (!empty($block_markets)) {
                        foreach ($block_markets as $block_market) {
                            if ($block_market['type'] == 'Sport') {
                                if ($block_market['event_type_id'] == $list_event['event_type']) {
                                    unset($list_events[$key]);
                                }
                            }

                            if ($block_market['type'] == 'Event') {
                                if ($block_market['event_id'] == $list_event['event_id']) {
                                    unset($list_events[$key]);
                                }
                            }
                        }
                    }


                    if (!isset($list_events[$key])) {
                        continue;
                    }
                }

                $event_id = $list_event['event_id'];
                $exchangeData[$event_id] = $list_event;
                $user_id = $_SESSION['my_userdata']['user_id'];
                $check_favourite = $this->Favourite_event_model->get_favourite_event(array('event_id' => $list_event['event_id'], 'user_id' =>  $user_id));


                if (!empty($check_favourite)) {
                    $exchangeData[$event_id]['is_favourite'] = true;
                } else {
                    $exchangeData[$event_id]['is_favourite'] = false;
                }

                $market_types = $this->Event_model->list_market_types(array('event_id' => $list_event['event_id']));


                if (!empty($market_types)) {

                    foreach ($market_types as $key2 => $market_type) {
                        if (get_user_type() == 'User') {
                            if (!empty($block_markets)) {

                                foreach ($block_markets as $block_market) {
                                    if ($block_market['type'] == 'Market') {
                                        if ($block_market['market_id'] == $market_type['market_id']) {
                                            unset($market_types[$key2]);
                                        }
                                    }

                                    if ($block_market['type'] == 'Event') {
                                        if ($block_market['market_id'] == $market_type['market_id']) {
                                            unset($market_types[$key2]);
                                        }
                                    }
                                }
                            }

                            if (!isset($market_types[$key])) {
                                continue;
                            }
                        }


                        // $market_id = str_replace('.','',$market_type['market_id']);
                        $market_id = $market_type['market_id'];
                        // p($market_id);
                        $exchangeData[$event_id]['market_types'][$market_id] = $market_type;
                        $runners = $this->Event_model->list_market_book_odds_runner(array(
                            'event_id' => $list_event['event_id'],
                            'market_id' => $market_type['market_id'],
                        ));
                        $exchangeData[$event_id]['market_types'][$market_id]['runners'] = $runners;
                        $exchangeData[$event_id]['market_types'][$market_id]['runners'][0]['exposure'] = 0;
                        $exchangeData[$event_id]['market_types'][$market_id]['runners'][1]['exposure'] = 0;


                        $user_id = $_SESSION['my_userdata']['user_id'];
                        $event_type = $list_event['event_type'];
                        $user_info = $this->User_info_model->get_user_info_by_userid($user_id, $event_type);

                        $exchangeData[$event_id]['market_types'][$market_id]['user_info'] = $user_info;



                        $bettings = $this->Betting_model->get_last_bet(array('user_id' => $user_id, 'market_id' => $market_id));

                        if (get_user_type() == 'User') {
                            if (!empty($bettings)) {
                                $exposure = get_user_market_exposure_by_marketid($market_id);


                                $runners = $exchangeData[$event_id]['market_types'][$market_id]['runners'];

                                if (!empty($runners)) {
                                    foreach ($runners as $key => $runner) {

                                        if (!empty($runner)) {
                                            $selection_id = $runner['selection_id'];


                                            $exchangeData[$event_id]['market_types'][$market_id]['runners'][$key]['exposure'] = isset($exposure[$selection_id]) ? $exposure[$selection_id] : 0;
                                        }
                                    }
                                }
                                $exchangeData[$event_id]['view_info'] = array();

                                // $exchangeData[$event_id]['market_types'][$market_id]['runners'][0]['exposure'] = $bettings->exposure_1;

                                // $exchangeData[$event_id]['market_types'][$market_id]['runners'][1]['exposure'] = $bettings->exposure_2;
                            }
                        } else if (get_user_type() == 'Master') {
                            $exposure = get_master_market_exposure_by_marketid($market_id);

                            $runners = $exchangeData[$event_id]['market_types'][$market_id]['runners'];

                            if (!empty($runners)) {
                                foreach ($runners as $key => $runner) {

                                    if (!empty($runner)) {
                                        $selection_id = $runner['selection_id'];


                                        $exchangeData[$event_id]['market_types'][$market_id]['runners'][$key]['exposure'] = isset($exposure[$selection_id]) ? $exposure[$selection_id] : 0;
                                    }
                                    $exchangeData[$event_id]['view_info'] = array();
                                }
                            }
                        } else if (get_user_type() == 'Super Master') {
                            $exposure = get_super_master_market_exposure_by_marketid($market_id);

                            $runners = $exchangeData[$event_id]['market_types'][$market_id]['runners'];

                            if (!empty($runners)) {
                                foreach ($runners as $key => $runner) {

                                    if (!empty($runner)) {
                                        $selection_id = $runner['selection_id'];


                                        $exchangeData[$event_id]['market_types'][$market_id]['runners'][$key]['exposure'] =  isset($exposure[$selection_id]) ? $exposure[$selection_id] : 0;
                                        $exchangeData[$event_id]['view_info'] = array();
                                    }
                                }
                            }
                        } else if (get_user_type() == 'Hyper Super Master') {
                            $exposure = get_hyper_super_master_market_exposure_by_marketid($market_id);

                            $runners = $exchangeData[$event_id]['market_types'][$market_id]['runners'];

                            if (!empty($runners)) {
                                foreach ($runners as $key => $runner) {

                                    if (!empty($runner)) {
                                        $selection_id = $runner['selection_id'];


                                        $exchangeData[$event_id]['market_types'][$market_id]['runners'][$key]['exposure'] =  isset($exposure[$selection_id]) ? $exposure[$selection_id] : 0;
                                        $exchangeData[$event_id]['view_info'] = array();
                                    }
                                }
                            }
                        } else if (get_user_type() == 'Admin') {
                            $exposure = get_admin_market_exposure_by_marketid($market_id);

                            $runners = $exchangeData[$event_id]['market_types'][$market_id]['runners'];

                            if (!empty($runners)) {
                                foreach ($runners as $key => $runner) {

                                    if (!empty($runner)) {
                                        $selection_id = $runner['selection_id'];


                                        $exchangeData[$event_id]['market_types'][$market_id]['runners'][$key]['exposure'] =  isset($exposure[$selection_id]) ? $exposure[$selection_id] : 0;
                                    }
                                }
                            }
                        } else if (get_user_type() == 'Super Admin') {
                            $exposure = get_super_admin_market_exposure_by_marketid($market_id);

                            $runners = $exchangeData[$event_id]['market_types'][$market_id]['runners'];

                            if (!empty($runners)) {
                                foreach ($runners as $key => $runner) {

                                    if (!empty($runner)) {
                                        $selection_id = $runner['selection_id'];


                                        $exchangeData[$event_id]['market_types'][$market_id]['runners'][$key]['exposure'] =  isset($exposure[$selection_id]) ? $exposure[$selection_id] : 0;

                                        $exchangeData[$event_id]['view_info'] = array();
                                    }
                                }
                            }
                        }
                    }
                    // $fancy_data = $this->Event_model->get_all_fancy_data($list_event['event_id']);
                    // $fantacyData[$list_event['event_id']] =  $fancy_data;
                }
            }
        }


        $dataArray['events'] = $exchangeData;

        $fancy_data = $this->Event_model->get_all_fancy_data($event_id);
        $dataArray['fancy_data'] = $fancy_data;
        // $dataArray['exchange_response'] = array();
        // $exhangeHtml = compress_htmlcode($this->load->viewPartial('exchangeHtml', $dataArray));
        // $data['exchangeHtml'] = $exhangeHtml;

        // $data['fancyHtml'] =  compress_htmlcode($this->load->viewPartial('fancy-list-html', $dataArray));
        echo json_encode($dataArray);
    }

    public function bettingList()
    {
        $market_id = $this->input->post('MarketId');
        $user_id = $_SESSION['my_userdata']['user_id'];
        $match_id = $this->input->post('matchId');

        $user_type = $_SESSION['my_userdata']['user_type'];


        if ($user_type == 'Master') {
            $dataValues = array(
                'user_id' => $user_id,
                'match_id' => $match_id
            );


            $bettings = get_master_betting_list($dataValues);
        } else if ($user_type == 'Super Master') {
            $dataValues = array(
                'user_id' => $user_id,
                'match_id' => $match_id
            );
            $bettings = get_supermaster_betting_list($dataValues);
        } else if ($user_type == 'Hyper Super Master') {
            $dataValues = array(
                'user_id' => $user_id,
                'match_id' => $match_id
            );
            $bettings = get_hyper_super_master_betting_list($dataValues);
        } else if ($user_type == 'Admin') {
            $dataValues = array(
                'user_id' => $user_id,
                'match_id' => $match_id
            );
            $bettings = get_admin_betting_list($dataValues);
        } else if ($user_type == 'Super Admin') {
            $dataValues = array(
                'user_id' => $user_id,
                'match_id' => $match_id
            );
            $bettings = get_super_admin_betting_list($dataValues);
        } else {
            $dataValues = array(
                'user_id' => $user_id,
                'match_id' => $match_id
            );
            $bettings = $this->Betting_model->get_bettings_list($dataValues);
        }


        $dataArray['bettings'] = $bettings;
        $exhangeHtml = $this->load->viewPartial('betting-list-html', $dataArray);
        $data['bettingHtml'] = $exhangeHtml;

        $exposure = number_format(count_total_exposure($user_id), 2);
        $balance = number_format(count_total_balance($user_id), 2);

        $data['balance'] = $balance;
        $data['exposure'] = $exposure;

        echo json_encode($data);
    }


    public function savebet()
    {
        $user_id = $_SESSION['my_userdata']['user_id'];
        $balance = count_total_balance($user_id);
        $stake = $this->input->post('stake');
        $loss = $this->input->post('loss');
        $price_val = $this->input->post('priceVal');
        $selection_id = $this->input->post('selectionId');
        $betting_type = $this->input->post('betting_type');
        $MarketId = $this->input->post('MarketId');
        $exposure1 = $this->input->post('exposure1');
        $exposure2 = $this->input->post('exposure2');
        $event_type = $this->input->post('event_type');

        $max_profit = max($exposure1, $exposure2);
        $max_loss = min($exposure1, $exposure2);
        $unmatch_bet = 'No';


        if ($exposure1)
            if ($betting_type === 'Match') {
                $exposure = get_user_market_exposure_by_marketid($MarketId);

                if (!empty($exposure)) {
                    $newexposure = 0;
                    if ($exposure1 < 0 && $exposure1 < $exposure2) {
                        $newexposure = $exposure1;
                    } else if ($exposure2 < 0 && $exposure2 < $exposure1) {
                        $newexposure = $exposure2;
                    }



                    $minExposure = min($exposure);

                    //    p('newexposure : '.$newexposure,0);
                    //    p('minexposure : '.$minExposure,0);
                    //    p('balance : '.$balance,0);
                    //    p('loss : '.$loss);

                    $newexposure = abs($newexposure);
                    $minExposure = abs($minExposure);

                    $totalbalance = abs($minExposure - $newexposure);


                    if ($balance < $totalbalance) {
                        $dataArray = array(
                            'success' => false,
                            'message' => 'Insufficient Balance'
                        );

                        echo json_encode($dataArray);
                        exit;
                    }
                } else {
                    if ($loss > $balance) {
                        $dataArray = array(
                            'success' => false,
                            'message' => 'Insufficient Balance'
                        );

                        echo json_encode($dataArray);
                        exit;
                    }
                }
            } else {
                if ($loss > $balance) {
                    $dataArray = array(
                        'success' => false,
                        'message' => 'Insufficient Balance'
                    );

                    echo json_encode($dataArray);
                    exit;
                }
            }




        $user_details = $this->User_model->getUserById($user_id);

        if (!empty($user_details)) {
            if ($user_details->is_betting_open == 'No') {
                $dataArray = array(
                    'success' => false,
                    'message' => 'Betting Rights is closed'
                );
                echo json_encode($dataArray);
                exit;
            }

            if ($user_details->is_locked == 'Yes') {
                $dataArray = array(
                    'success' => false,
                    'message' => 'Your account is locked by your superior.'
                );
                echo json_encode($dataArray);
                exit;
            }

            if ($user_details->is_closed == 'Yes') {
                $dataArray = array(
                    'success' => false,
                    'message' => 'Your account is closed by your superior.'
                );
                echo json_encode($dataArray);
                exit;
            }
        }




        if ($betting_type == 'Fancy') {
            $sport_id = 999;
        } else {
            $sport_id = $event_type;
        }

        $user_info = $this->User_info_model->get_user_info_by_userid($user_id, $sport_id);

        if (!empty($user_info)) {

            $market_odds_detail = $this->Market_book_odds_model->get_market_book_odds_by_market_id($MarketId);

            if ($betting_type == 'Fancy') {
                if ($user_info->min_stake > $stake) {
                    $dataArray = array(
                        'success' => false,
                        'message' => 'Min Stake allowed is: ' . $user_info->min_stake
                    );
                    echo json_encode($dataArray);
                    exit;
                }

                if ($user_info->max_stake < $stake) {
                    $dataArray = array(
                        'success' => false,
                        'message' => 'Max Stake allowed is: ' . $user_info->max_stake
                    );
                    echo json_encode($dataArray);
                    exit;
                }
            } else if ($market_odds_detail->inplay == 1) {
                if ($user_info->min_stake > $stake) {
                    $dataArray = array(
                        'success' => false,
                        'message' => 'Min Stake allowed is: ' . $user_info->min_stake
                    );
                    echo json_encode($dataArray);
                    exit;
                }

                if ($user_info->max_stake < $stake) {
                    $dataArray = array(
                        'success' => false,
                        'message' => 'Max Stake allowed is: ' . $user_info->max_stake
                    );
                    echo json_encode($dataArray);
                    exit;
                }


                if ($user_info->max_profit <  $max_profit) {
                    $dataArray = array(
                        'success' => false,
                        'message' => 'Max Profit allowed is: ' . $user_info->max_profit
                    );
                    echo json_encode($dataArray);
                    exit;
                }


                if ($user_info->max_loss <  abs($max_loss)) {
                    $dataArray = array(
                        'success' => false,
                        'message' => 'Max Loss allowed is: ' . abs($user_info->max_loss)
                    );

                    echo json_encode($dataArray);
                    exit;
                }

                if ($user_info->lock_bet ==  "Yes") {
                    $dataArray = array(
                        'success' => false,
                        'message' => 'Betting Rights is locked'
                    );

                    echo json_encode($dataArray);
                    exit;
                }

                if ($user_info->min_odds > $price_val) {
                    $dataArray = array(
                        'success' => false,
                        'message' => 'Minimum odds allowed is : ' . $user_info->min_odds
                    );

                    echo json_encode($dataArray);
                    exit;
                }

                if ($user_info->max_odds < $price_val) {
                    $dataArray = array(
                        'success' => false,
                        'message' => 'Maximum odds allowed is : ' . $user_info->max_odds
                    );

                    echo json_encode($dataArray);
                    exit;
                }
            } else if ($market_odds_detail->inplay == 0) {

                if ($user_info->pre_inplay_stake < $stake) {
                    $dataArray = array(
                        'success' => false,
                        'message' => 'Max Pre Inplay Stake allowed is: ' . $user_info->pre_inplay_stake
                    );
                    echo json_encode($dataArray);
                    exit;
                }


                if ($user_info->pre_inplay_profit <  $max_profit) {
                    $dataArray = array(
                        'success' => false,
                        'message' => 'Max Pre Inplay Profit allowed is: ' . $user_info->pre_inplay_profit
                    );
                    echo json_encode($dataArray);
                    exit;
                }

                if ($user_info->lock_bet ==  "Yes") {
                    $dataArray = array(
                        'success' => false,
                        'message' => 'Betting Rights is locked'
                    );

                    echo json_encode($dataArray);
                    exit;
                }

                if ($user_info->min_odds > $price_val) {
                    $dataArray = array(
                        'success' => false,
                        'message' => 'Minimum odds allowed is : ' . $user_info->min_odds
                    );

                    echo json_encode($dataArray);
                    exit;
                }

                if ($user_info->max_odds < $price_val) {
                    $dataArray = array(
                        'success' => false,
                        'message' => 'Maximum odds allowed is : ' . $user_info->max_odds
                    );

                    echo json_encode($dataArray);
                    exit;
                }
            }


            if ($user_info->bet_delay > 0) {
                sleep($user_info->bet_delay);
            }


            if ($betting_type == 'Match') {
                $data1 = array(
                    'market_id' => $this->input->post('MarketId'),
                    'event_id' => $this->input->post('matchId'),
                    'selection_id' => $selection_id
                );

                $is_back = $this->input->post('isback');

                if ($is_back == 1) {
                    $data2 = array(
                        'back_1_price' => (float) $price_val,
                        'back_2_price' => (float) $price_val,
                        'back_3_price' => (float) $price_val,
                    );
                } else {
                    $data2 = array(
                        'lay_1_price' => (float) $price_val,
                        'lay_2_price' => (float) $price_val,
                        'lay_3_price' => (float) $price_val,
                    );
                }

                // $check_current_odds = check_current_odds($data1, $data2);

                $check_current_odds = $this->Event_model->check_active_odds($data1);

                if (!empty($check_current_odds)) {
                    $back_price = $check_current_odds->back_1_price;
                    $lay_price = $check_current_odds->lay_1_price;

                    if ($is_back) {
                        if ($price_val < $back_price) {
                            $dataArray = array(
                                'success' => false,
                                'message' => 'Unmatched Bet not allowed'
                            );
                            echo json_encode($dataArray);
                            exit;
                        } else  if ($back_price >= $price_val) {
                            $price_val = $back_price;
                        }
                    }


                    if ($is_back == 0) {
                        if ($price_val > $lay_price) {
                            $dataArray = array(
                                'success' => false,
                                'message' => 'Unmatched Bet not allowed'
                            );
                            echo json_encode($dataArray);
                            exit;
                        } else  if ($lay_price <= $price_val) {
                            $price_val = $back_price;
                        }
                    }
                }
                if (!empty($check_current_odds)) {
                    $dataArray = array(
                        'success' => true,
                        'message' => 'Bet Placed Successfully'
                    );
                    echo json_encode($dataArray);
                } else {
                    $unmatch_bet = 'Yes';
                    if ($user_info->unmatch_bet == 'No') {
                        $dataArray = array(
                            'success' => false,
                            'message' => 'Unmatched Bet not allowed'
                        );
                        echo json_encode($dataArray);
                        exit;
                    }
                }
            } else {
                $dataArray = array(
                    'success' => true,
                    'message' => 'Bet Placed Successfully'
                );
                echo json_encode($dataArray);
            }
        }


        $dataArray = array(
            'match_id' => $this->input->post('matchId'),
            'selection_id' => $this->input->post('selectionId'),
            'is_back' => $this->input->post('isback'),
            'place_name' => $this->input->post('placeName'),
            'stake' => $this->input->post('stake'),
            'price_val' => $price_val,
            'p_l' => $this->input->post('p_l'),
            'market_id' => $this->input->post('MarketId'),
            'user_id' => $_SESSION['my_userdata']['user_id'],
            'betting_type' => $this->input->post('betting_type'),
            'profit' => $this->input->post('profit'),
            'loss' => $this->input->post('loss'),
            'exposure_1' => $this->input->post('exposure1'),
            'exposure_2' => $this->input->post('exposure2'),
            'ip_address' =>  $_SERVER['REMOTE_ADDR'],
            'unmatch_bet' => $unmatch_bet
        );

        // p($dataArray['exchange_response']);
        $betting_id =  $this->load->Betting_model->addBetting($dataArray);



        /**************************Get All Superior and save betting time settings*******  */
        $userDetail = $this->User_model->getUserById($user_id);

        if (!empty($userDetail)) {

            /*************Users**************** */
            $bettingSettingData = array(
                'user_id' => $user_id,
                'betting_id' => $betting_id,
                'casino_partnership' => $userDetail->casino_partnership,
                'partnership' => $userDetail->partnership,
                'teenpati_partnership' => $userDetail->teenpati_partnership,
                'master_commission' => $userDetail->master_commision,
                'sessional_commission' => $userDetail->sessional_commision,
                'user_type' => $userDetail->user_type,
                'created_at' => date('Y-m-d H:i:s')
            );
            $setting_id = $this->Masters_betting_settings_model->addBettingSetting($bettingSettingData);
            /*************Users**************** */


            if (!empty($setting_id)) {
                /*************Masters**************** */

                $master_id = get_master_id();
                $masterDetail = $this->User_model->getUserById($master_id);
                if (!empty($masterDetail)) {
                    $bettingSettingData = array(
                        'user_id' => $master_id,
                        'betting_id' => $betting_id,
                        'casino_partnership' => $masterDetail->casino_partnership,
                        'partnership' => $masterDetail->partnership,
                        'teenpati_partnership' => $masterDetail->teenpati_partnership,
                        'master_commission' => $masterDetail->master_commision,
                        'sessional_commission' => $masterDetail->sessional_commision,
                        'created_at' => date('Y-m-d H:i:s'),
                        'user_type' => $masterDetail->user_type,

                    );
                    $setting_id = $this->Masters_betting_settings_model->addBettingSetting($bettingSettingData);
                    /*************Masters**************** */

                    /*************Super Master**************** */

                    if (!empty($setting_id)) {
                        $super_master_id = $masterDetail->master_id;
                        $superMasterDetail = $this->User_model->getUserById($super_master_id);
                        if (!empty($superMasterDetail)) {
                            $bettingSettingData = array(
                                'user_id' => $super_master_id,
                                'betting_id' => $betting_id,
                                'casino_partnership' => $superMasterDetail->casino_partnership,
                                'partnership' => $superMasterDetail->partnership,
                                'teenpati_partnership' => $superMasterDetail->teenpati_partnership,
                                'master_commission' => $superMasterDetail->master_commision,
                                'sessional_commission' => $superMasterDetail->sessional_commision,
                                'created_at' => date('Y-m-d H:i:s'),
                                'user_type' => $superMasterDetail->user_type,

                            );
                            $setting_id = $this->Masters_betting_settings_model->addBettingSetting($bettingSettingData);
                            /*************Super Master**************** */

                            /*************Hyper Super Master**************** */

                            if (!empty($setting_id)) {
                                $hyper_super_master_id = $superMasterDetail->master_id;
                                $hyperSuperMasterDetail = $this->User_model->getUserById($hyper_super_master_id);
                                if (!empty($hyperSuperMasterDetail)) {
                                    $bettingSettingData = array(
                                        'user_id' => $hyper_super_master_id,
                                        'betting_id' => $betting_id,
                                        'casino_partnership' => $hyperSuperMasterDetail->casino_partnership,
                                        'partnership' => $hyperSuperMasterDetail->partnership,
                                        'teenpati_partnership' => $hyperSuperMasterDetail->teenpati_partnership,
                                        'master_commission' => $hyperSuperMasterDetail->master_commision,
                                        'sessional_commission' => $hyperSuperMasterDetail->sessional_commision,
                                        'created_at' => date('Y-m-d H:i:s'),
                                        'user_type' => $hyperSuperMasterDetail->user_type,

                                    );
                                    $setting_id = $this->Masters_betting_settings_model->addBettingSetting($bettingSettingData);
                                    /*************Hyper Super Master**************** */

                                    /*************Admnin**************** */
                                    if (!empty($setting_id)) {
                                        $admin_id = $hyperSuperMasterDetail->master_id;
                                        $adminDetail = $this->User_model->getUserById($admin_id);
                                        if (!empty($adminDetail)) {
                                            $bettingSettingData = array(
                                                'user_id' => $admin_id,
                                                'betting_id' => $betting_id,
                                                'casino_partnership' => $adminDetail->casino_partnership,
                                                'partnership' => $adminDetail->partnership,
                                                'teenpati_partnership' => $adminDetail->teenpati_partnership,
                                                'master_commission' => $adminDetail->master_commision,
                                                'sessional_commission' => $adminDetail->sessional_commision,
                                                'created_at' => date('Y-m-d H:i:s'),
                                                'user_type' => $adminDetail->user_type,

                                            );
                                            $setting_id = $this->Masters_betting_settings_model->addBettingSetting($bettingSettingData);
                                            /*************Admnin**************** */

                                            /*************Super Admin**************** */

                                            if (!empty($setting_id)) {
                                                $super_admin_id = $adminDetail->master_id;
                                                $superAdminDetail = $this->User_model->getUserById($super_admin_id);
                                                if (!empty($adminDetail)) {
                                                    $bettingSettingData = array(
                                                        'user_id' => $super_admin_id,
                                                        'betting_id' => $betting_id,
                                                        'casino_partnership' => $superAdminDetail->casino_partnership,
                                                        'partnership' => $superAdminDetail->partnership,
                                                        'teenpati_partnership' => $superAdminDetail->teenpati_partnership,
                                                        'master_commission' => $superAdminDetail->master_commision,
                                                        'sessional_commission' => $superAdminDetail->sessional_commision,
                                                        'created_at' => date('Y-m-d H:i:s'),
                                                        'user_type' => $superAdminDetail->user_type,

                                                    );
                                                    $setting_id = $this->Masters_betting_settings_model->addBettingSetting($bettingSettingData);
                                                }
                                            }
                                            /*************Super Admin**************** */
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        /**************************Get All Superior and save betting time settings*******  */
    }

    public function getfancydata1()
    {
        $fancy_data = get_fancy_by_id();
        $marketId = '1.172980694';

        $dataValues = array(
            'market_id' => $marketId,
            'response' => $fancy_data
        );

        $this->Fancy_data_model->addFancyData($dataValues);
    }

    public function getfancydata($bfid = null)
    {
        if ($bfid === null) {
            $bfid = 1.172980694;
        }

        $dataValues = array(
            'market_id' => $bfid
        );


        $fancyData = $this->Fancy_data_model->get_fancy_data($dataValues);

        $html = '';
        if (!empty($fancyData->response)) {
            $fancyData =  json_decode($fancyData->response);


            $dataArray['fancyData'] = $fancyData->LambiData;
            $html .= $this->load->viewPartial('fancy-list-html', $dataArray);
        }

        // echo $html;
        echo json_encode(array('fancyData' => $html));
    }

    //API Calling in 2 days
    public function addEventTypes()
    {
        $api_provider = getCustomConfigItem('api_provider');

        if ($api_provider == 'SANJAY') {
            $event_types =  json_decode(listEventTypes());
            if (!empty($event_types)) {
                foreach ($event_types as $event_type) {
                    if ($event_type->eventType == 4 ||  $event_type->eventType == 1 ||  $event_type->eventType == 2) {
                        $dataArray = array(
                            'event_type' => $event_type->eventType,
                            'name' => $event_type->name,
                            'market_count' => $event_type->marketCount,
                        );

                        $this->Event_model->addEventType($dataArray);
                    }
                }
            }
        } else if ($api_provider == 'BETFAIR') {
            $event_types =  json_decode(listEventTypesBetfair());

            if (!empty($event_types)) {
                foreach ($event_types as $event_type) {
                    if ($event_type->sport_id == 4 ||  $event_type->sport_id == 1 ||  $event_type->sport_id == 2) {
                        $dataArray = array(
                            'event_type' => $event_type->sport_id,
                            'name' => $event_type->sport_name,
                            'market_count' => $event_type->market_count,
                        );

                        $this->Event_model->addEventType($dataArray);
                    }
                }
            }
        }

        return true;
    }

    public function getEventTypes()
    {
        return listEventTypes();
    }

    //API Calling in 2 days
    public function addCompetition()
    {
        $api_provider = getCustomConfigItem('api_provider');
        $event_types = $this->Event_model->getEventTypes();

        if ($api_provider == 'SANJAY') {
            if (!empty($event_types)) {
                foreach ($event_types as $event_type) {
                    if ($event_type['event_type'] == 4 || $event_type['event_type'] == 1 || $event_type['event_type'] == 2) {
                        // p($event_type);
                        $competitions = json_decode(listCompetitions($event_type['event_type']));


                        if (!empty($competitions)) {
                            foreach ($competitions as $competition) {
                                $dataArray = array(
                                    'event_type' => $event_type['event_type'],
                                    'competition_id' => $competition->competition->id,
                                    'competition_name' => $competition->competition->name,
                                    'market_count' => $competition->marketCount,
                                    'competition_region' => $competition->competitionRegion,
                                );
                                $this->Event_model->addCompetition($dataArray);
                            }
                        }
                    }
                }
            }
        } else if ($api_provider == 'BETFAIR') {

            if (!empty($event_types)) {
                foreach ($event_types as $event_type) {
                    if ($event_type['event_type'] == 4 || $event_type['event_type'] == 1 || $event_type['event_type'] == 2) {
                        // p($event_type);
                        $competitions = json_decode(listCompetitionsBetfair($event_type['event_type']));


                        if (!empty($competitions)) {
                            foreach ($competitions as $competition) {
                                $dataArray = array(
                                    'event_type' => $event_type['event_type'],
                                    'competition_id' => $competition->competition_id,
                                    'competition_name' => $competition->competition_name,
                                    'market_count' => $competition->market_count,
                                    'competition_region' => $competition->competition_region,
                                );
                                $this->Event_model->addCompetition($dataArray);
                            }
                        }
                    }
                }
            }
        }
        return true;
    }

    //API Calling in 1 days

    public function addEvents()
    {

        $competitions = $this->Event_model->getCompetitions();

        if (!empty($competitions)) {


            $api_provider = getCustomConfigItem('api_provider');

            if ($api_provider == 'SANJAY') {


                foreach ($competitions as $competition) {
                    $events = json_decode(listEvents($competition['event_type'], $competition['competition_id']));
                    if (!empty($events)) {
                        foreach ($events as $event) {
                            $dataArray = array(
                                'competition_id' => $competition['competition_id'],
                                'event_type' => $competition['event_type'],
                                'event_id' => $event->event->id,
                                'event_name' => $event->event->name,
                                'country_code' => $event->event->countryCode,
                                'timezone' => $event->event->timezone,
                                'open_date' => $event->event->openDate,
                                'market_count' => $event->marketCount,
                                'scoreboard_id' => $event->scoreboard_id,
                                'selections' => $event->selections,
                                'liability_type' => $event->liability_type,
                                'undeclared_markets' => $event->undeclared_markets,
                                'is_active' => 'Yes'
                            );
                            $this->Event_model->addEvents($dataArray);
                        }
                    }
                }
            } else if ($api_provider == 'BETFAIR') {
                foreach ($competitions as $competition) {

                    $events = json_decode(listEventsBetfair($competition['competition_id']));

                    if (!empty($events)) {
                        foreach ($events as $event) {
                            $dataArray = array(
                                'competition_id' => $competition['competition_id'],
                                'event_type' => $event->sport_id,
                                'event_id' => $event->match_id,
                                'event_name' => $event->match_name,
                                'country_code' => $event->countryCode,
                                'timezone' => $event->timezone,
                                'open_date' => $event->open_date,
                                'market_count' => '',
                                'scoreboard_id' => '',
                                'selections' => '',
                                'liability_type' => '',
                                'undeclared_markets' => '',
                                'is_active' => 'Yes',
                                // 'status' => $event->status,
                            );
                            $this->Event_model->addEvents($dataArray);
                        }
                    }
                }
            }
        }
        return true;
    }

    public function addMarketTypes($sport_id = null)
    {
        $events = $this->Event_model->getEvents();

        if (!empty($events)) {
            foreach ($events as $event) {

                $api_provider = getCustomConfigItem('api_provider');

                if ($api_provider == 'SANJAY') {
                    $listMarketTypes = json_decode(listMarketTypes($event['event_id']));


                    if (!empty($listMarketTypes)) {
                        foreach ($listMarketTypes as $market) {
                            $dataArray = array(
                                'event_id' => $event['event_id'],
                                'market_name' => $market->marketName,
                                'market_id' => $market->marketId,
                                'market_start_time' => $market->marketStartTime,
                                'total_matched' => $market->totalMatched,
                                'runner_1_selection_id' => $market->runners[0]->selectionId,
                                'runner_1_runner_name' => $market->runners[0]->runnerName,
                                'runner_1_handicap' => $market->runners[0]->handicap,
                                'runner_1_sort_priority' => $market->runners[0]->sortPriority,
                                'runner_2_selection_id' => $market->runners[1]->selectionId,
                                'runner_2_runner_name' => $market->runners[1]->runnerName,
                                'runner_2_handicap' => $market->runners[1]->handicap,
                                'runner_2_sort_priority' => $market->runners[1]->sortPriority,
                            );

                            // p($dataArray, 0);

                            $this->Event_model->addMarketTypes($dataArray);
                        }
                    }
                } else if ($api_provider == 'BETFAIR') {

                    if ($event['event_type'] == 4) {
                        $listMarketTypes = json_decode(listMarketTypesBetfair($event['event_id']));

                        $markets_arr = array(
                            '0' => 'Match Odds',
                            '1' => 'To Win the Toss',

                        );

                        if (!empty($listMarketTypes)) {
                            foreach ($listMarketTypes as $market) {
                                if (in_array($market->market_name, $markets_arr)) {
                                    $match_id = $market->matches_id;
                                    $market_id = $market->market_id;
                                    $data = json_decode(listMarketBookOddsBetfair($match_id, $market_id));

 
                                     $dataArray = array(
                                        'event_id' => $market->matches_id,
                                        'market_name' => $market->market_name,
                                        'market_id' => $market->market_id,
                                        'market_start_time' => $market->market_start_time,
                                        'total_matched' => $market->total_matched,
                                        'runner_1_selection_id' => $data->runners[0]->selectionId,
                                        'runner_1_runner_name' => $market->runners[0]->runnerName,
                                        'runner_1_handicap' => $data->runners[0]->handicap,
                                        'runner_1_sort_priority' => $market->runners[0]->sortPriority,
                                        'runner_2_selection_id' => $data->runners[1]->selectionId,
                                        'runner_2_runner_name' => $market->runners[1]->runnerName,
                                        'runner_2_handicap' => $data->runners[1]->handicap,
                                        'runner_2_sort_priority' => $market->runners[1]->sortPriority,
                                    ); 
                                   // $this->Event_model->addMarketTypes($dataArray);

                                    $dataArray = array(
                                       'market_id' => $data->marketId,
                                       'is_market_data_delayed' => $data->isMarketDataDelayed,
                                       'status' => $data->status,
                                       'bet_delay' => $data->betDelay,
                                       'bsp_reconciled' => $data->bspReconciled,
                                       'complete' => $data->complete,
                                       'inplay' => $data->inplay,
                                       'number_of_winners' => $data->numberOfWinners,
                                       'number_of_runners' => $data->numberOfRunners,
                                       'number_of_active_runners' => $data->numberOfActiveRunners,
                                       'last_match_time' => $data->lastMatchTime,
                                       'total_matched' => $data->totalMatched,
                                       'total_available' => $data->totalAvailable,
                                       'cross_matching' => $data->crossMatching,
                                       'runners_voidable' => $data->runnersVoidable,
                                       'version' => $data->version,
           
                                   );
           
                                   p($dataArray,0);

                                   p($data);
           
                                   $market_book_odd_id =  $this->Event_model->addMarketBookOdds($dataArray);
           
                                   if ($market_book_odd_id) {
                                       if (!empty($listMarketBookOdd->runners)) {
                                           foreach ($listMarketBookOdd->runners as $runner) {
                                               $dataArray = array(
                                                   'market_book_odd_id' => $market_book_odd_id,
                                                   'market_id' => $listMarketBookOdd->marketId,
                                                   'event_id' => $event_id,
                                                   'selection_id' => $runner->selectionId,
                                                   'handicap' => $runner->handicap,
                                                   'status' => $runner->status,
                                                   'last_price_traded' => $runner->lastPriceTraded,
                                                   'total_matched' => $runner->totalMatched,
                                                   'back_1_price' => isset($runner->ex->availableToBack[0]->price) ? $runner->ex->availableToBack[0]->price : '',
                                                   'back_2_price' => isset($runner->ex->availableToBack[1]->price) ? $runner->ex->availableToBack[1]->price : '',
                                                   'back_3_price' => isset($runner->ex->availableToBack[2]->price) ? $runner->ex->availableToBack[2]->price : '',
                                                   'back_1_size' => isset($runner->ex->availableToBack[0]->size) ? $runner->ex->availableToBack[0]->size : '',
                                                   'back_2_size' => isset($runner->ex->availableToBack[1]->size) ? $runner->ex->availableToBack[1]->size : '',
                                                   'back_3_size' => isset($runner->ex->availableToBack[2]->size) ? $runner->ex->availableToBack[2]->size : '',
                                                   'lay_1_price' => isset($runner->ex->availableToLay[0]->price) ? $runner->ex->availableToLay[0]->price : '',
                                                   'lay_2_price' => isset($runner->ex->availableToLay[1]->price) ? $runner->ex->availableToLay[1]->price : '',
                                                   'lay_3_price' => isset($runner->ex->availableToLay[2]->price) ? $runner->ex->availableToLay[2]->price : '',
                                                   'lay_1_size' => isset($runner->ex->availableToLay[0]->size) ? $runner->ex->availableToLay[0]->size : '',
                                                   'lay_2_size' => isset($runner->ex->availableToLay[1]->size) ? $runner->ex->availableToLay[1]->size : '',
                                                   'lay_3_size' => isset($runner->ex->availableToLay[2]->size) ? $runner->ex->availableToLay[2]->size : '',
                                               );
           
                                               $this->Event_model->addMarketBookOddsRunners($dataArray);
                                           }
                                       }
                                   }
                                } else {
                                }

                                // $dataArray = array(
                                //     'event_id' => $event['event_id'],
                                //     'market_name' => $market->marketName,
                                //     'market_id' => $market->marketId,
                                //     'market_start_time' => $market->marketStartTime,
                                //     'total_matched' => $market->totalMatched,
                                //     'runner_1_selection_id' => $market->runners[0]->selectionId,
                                //     'runner_1_runner_name' => $market->runners[0]->runnerName,
                                //     'runner_1_handicap' => $market->runners[0]->handicap,
                                //     'runner_1_sort_priority' => $market->runners[0]->sortPriority,
                                //     'runner_2_selection_id' => $market->runners[1]->selectionId,
                                //     'runner_2_runner_name' => $market->runners[1]->runnerName,
                                //     'runner_2_handicap' => $market->runners[1]->handicap,
                                //     'runner_2_sort_priority' => $market->runners[1]->sortPriority,
                                // );

                                // // p($dataArray, 0);

                                // $this->Event_model->addMarketTypes($dataArray);
                            }
                        }
                    }
                }
            }
        }
    }

    public function addMarketBookOdds($sport_id = null)
    {
        $market_types = $this->Event_model->getMarketTypeIds();

        if (!empty($market_types)) {
            $market_ids = '';
            $event_ids = array();
            foreach ($market_types as $market_type) {
                $market_ids .= $market_type['market_id'] . ',';
                $market_id = str_replace('.', '_', $market_type['market_id']);
                $event_ids[$market_id] = array('event_id' => $market_type['event_id']);
            }



            $market_ids = rtrim($market_ids, ',');


            $market_ids = explode(',', $market_ids);


            $lenght = 30;

            $totalLength = sizeof($market_ids);

            $totalRequest = ceil($totalLength / $lenght);


            $j = 1;
            for ($i = 0; $i < $totalRequest; $i++) {
                $marketid_reqs = array_slice($market_ids, $i * $lenght, $lenght);



                $j++;
                $marketid_reqs = implode(',', $marketid_reqs);
                // p($marketid_reqs);
                $listMarketBookOdds = json_decode(listMarketBookOdds($marketid_reqs));


                if (!empty($listMarketBookOdds)) {
                    foreach ($listMarketBookOdds as $listMarketBookOdd) {
                        $marketId = str_replace('.', '_', $listMarketBookOdd->marketId);
                        $event_id = $event_ids[$marketId]['event_id'];
                        $dataArray = array(
                            'market_id' => $listMarketBookOdd->marketId,
                            'is_market_data_delayed' => $listMarketBookOdd->isMarketDataDelayed,
                            'status' => $listMarketBookOdd->status,
                            'bet_delay' => $listMarketBookOdd->betDelay,
                            'bsp_reconciled' => $listMarketBookOdd->bspReconciled,
                            'complete' => $listMarketBookOdd->complete,
                            'inplay' => $listMarketBookOdd->inplay,
                            'number_of_winners' => $listMarketBookOdd->numberOfWinners,
                            'number_of_runners' => $listMarketBookOdd->numberOfRunners,
                            'number_of_active_runners' => $listMarketBookOdd->numberOfActiveRunners,
                            'last_match_time' => $listMarketBookOdd->lastMatchTime,
                            'total_matched' => $listMarketBookOdd->totalMatched,
                            'total_available' => $listMarketBookOdd->totalAvailable,
                            'cross_matching' => $listMarketBookOdd->crossMatching,
                            'runners_voidable' => $listMarketBookOdd->runnersVoidable,
                            'version' => $listMarketBookOdd->version,

                        );


                        $market_book_odd_id =  $this->Event_model->addMarketBookOdds($dataArray);

                        if ($market_book_odd_id) {
                            if (!empty($listMarketBookOdd->runners)) {
                                foreach ($listMarketBookOdd->runners as $runner) {
                                    $dataArray = array(
                                        'market_book_odd_id' => $market_book_odd_id,
                                        'market_id' => $listMarketBookOdd->marketId,
                                        'event_id' => $event_id,
                                        'selection_id' => $runner->selectionId,
                                        'handicap' => $runner->handicap,
                                        'status' => $runner->status,
                                        'last_price_traded' => $runner->lastPriceTraded,
                                        'total_matched' => $runner->totalMatched,
                                        'back_1_price' => isset($runner->ex->availableToBack[0]->price) ? $runner->ex->availableToBack[0]->price : '',
                                        'back_2_price' => isset($runner->ex->availableToBack[1]->price) ? $runner->ex->availableToBack[1]->price : '',
                                        'back_3_price' => isset($runner->ex->availableToBack[2]->price) ? $runner->ex->availableToBack[2]->price : '',
                                        'back_1_size' => isset($runner->ex->availableToBack[0]->size) ? $runner->ex->availableToBack[0]->size : '',
                                        'back_2_size' => isset($runner->ex->availableToBack[1]->size) ? $runner->ex->availableToBack[1]->size : '',
                                        'back_3_size' => isset($runner->ex->availableToBack[2]->size) ? $runner->ex->availableToBack[2]->size : '',
                                        'lay_1_price' => isset($runner->ex->availableToLay[0]->price) ? $runner->ex->availableToLay[0]->price : '',
                                        'lay_2_price' => isset($runner->ex->availableToLay[1]->price) ? $runner->ex->availableToLay[1]->price : '',
                                        'lay_3_price' => isset($runner->ex->availableToLay[2]->price) ? $runner->ex->availableToLay[2]->price : '',
                                        'lay_1_size' => isset($runner->ex->availableToLay[0]->size) ? $runner->ex->availableToLay[0]->size : '',
                                        'lay_2_size' => isset($runner->ex->availableToLay[1]->size) ? $runner->ex->availableToLay[1]->size : '',
                                        'lay_3_size' => isset($runner->ex->availableToLay[2]->size) ? $runner->ex->availableToLay[2]->size : '',
                                    );

                                    $this->Event_model->addMarketBookOddsRunners($dataArray);
                                }
                            }
                        }
                    }
                }
            }
        }

        return true;
    }

    public function addMarketBookSession($match_id = null)
    {
        $market_types = $this->Event_model->getMarketTypeIds();

        if (!empty($market_types)) {
            foreach ($market_types as $market_type) {

                $listMarketBookSession = json_decode(listMarketBookSession($market_type['event_id']));

                if (!empty($listMarketBookSession)) {

                    if (!isset($listMarketBookSession->message)) {
                        foreach ($listMarketBookSession as $session) {
                            $dataArray = array(
                                'match_id' => $market_type['event_id'],
                                'selection_id' => $session->SelectionId,
                                'runner_name' => $session->RunnerName,
                                'lay_price1' => $session->LayPrice1,
                                'lay_size1' => $session->LaySize1,
                                'back_price1' => $session->BackPrice1,
                                'back_size1' => $session->BackSize1,
                                'game_status' => $session->GameStatus,
                                'mark_status' => $session->MarkStatus,

                            );
                            $this->Event_model->addMarketBookOddsFancy($dataArray);
                        }
                    }
                }
            }
        }
    }


    public function listEvents($EventTypeID = null, $CompetitionID = null)
    {
        return listEvents($EventTypeID, $CompetitionID);
    }

    public function listMarketTypes($EventID = null)
    {
        return listMarketTypes($EventID);
    }

    public function listMarketRunner($MarketID = null)
    {
        return listMarketRunner($MarketID);
    }

    public function listMarketBookOdds($market_id = null)
    {
        return listMarketBookOdds($market_id);
    }

    public function listMarketBookSession($match_id = null)
    {
        return listMarketBookSession($match_id);
    }

    public function getAllData()
    {

        $this->addMarketBookOdds();
        $list_events = $this->Event_model->getEvents();


        if (!empty($list_events)) {
            foreach ($list_events as $list_event) {

                $listMarketBookSession = json_decode(listMarketBookSession($list_event['event_id']));

                // p($listMarketBookSession,0);

                if (!empty($listMarketBookSession)) {
                    if (!isset($listMarketBookSession->message)) {

                        foreach ($listMarketBookSession as $session) {
                            if (isset($session->SelectionId) && isset($session->RunnerName) && isset($session->LayPrice1)) {

                                $sessionArray = array(
                                    'match_id' => $list_event['event_id'],
                                    'selection_id' => $session->SelectionId,
                                    'runner_name' => $session->RunnerName,
                                    'lay_price1' => $session->LayPrice1,
                                    'lay_size1' => $session->LaySize1,
                                    'back_price1' => $session->BackPrice1,
                                    'back_size1' => $session->BackSize1,
                                    'game_status' => $session->GameStatus,
                                    'mark_status' => $session->MarkStatus,
                                    'is_active' => 'Yes'
                                );

                                $this->Event_model->addMarketBookOddsFancy($sessionArray);
                            }
                        }



                        // $this->Event_model->addMarketBookOddsFancy($sessionArray);

                    }
                }
            }
        }
        $this->sendresponse();

        return true;
    }

    // public function getAllData()
    // {

    //     $this->addMarketBookOdds();
    //     $list_events = $this->Event_model->getEvents();


    //     if (!empty($list_events)) {
    //         foreach ($list_events as $list_event) {

    //             $listMarketBookSession = json_decode(listMarketBookSession($list_event['event_id']));

    //             // p($listMarketBookSession,0);

    //             if (!empty($listMarketBookSession)) {
    //                 if (!isset($listMarketBookSession->message)) {

    //                     foreach ($listMarketBookSession as $session) {
    //                         if (isset($session->SelectionId) && isset($session->RunnerName) && isset($session->LayPrice1)) {

    //                             $sessionArray = array(
    //                                 'match_id' => $list_event['event_id'],
    //                                 'selection_id' => $session->SelectionId,
    //                                 'runner_name' => $session->RunnerName,
    //                                 'lay_price1' => $session->LayPrice1,
    //                                 'lay_size1' => $session->LaySize1,
    //                                 'back_price1' => $session->BackPrice1,
    //                                 'back_size1' => $session->BackSize1,
    //                                 'game_status' => $session->GameStatus,
    //                                 'mark_status' => $session->MarkStatus,
    //                                 'is_active' => 'Yes'
    //                             );

    //                             $this->Event_model->addMarketBookOddsFancy($sessionArray);
    //                         }
    //                     }



    //                     // $this->Event_model->addMarketBookOddsFancy($sessionArray);

    //                 }
    //             }
    //         }
    //     }
    //     $this->sendresponse();

    //     return true;
    // }

    public function sendresponse()
    {
        $data = array();
        $list_events = $this->Event_model->list_events($data);
        $exchangeData = array();
        $fantacyData = array();
        if (!empty($list_events)) {


            foreach ($list_events as $key => $list_event) {

                $event_id = $list_event['event_id'];

                $exchangeData[$event_id] = $list_event;


                $market_types = $this->Event_model->list_market_types(array('event_id' => $list_event['event_id']));

                if (!empty($market_types)) {

                    foreach ($market_types as $market_type) {
                        $market_id = $market_type['market_id'];
                        $exchangeData[$event_id]['market_types'][$market_id] = $market_type;
                        $runners = $this->Event_model->list_market_book_odds_runner(array(
                            'event_id' => $list_event['event_id'],
                            'market_id' => $market_type['market_id'],
                        ));
                        $exchangeData[$event_id]['market_types'][$market_id]['runner'] = $runners;
                    }
                }

                $fancy_data = $this->Event_model->get_fancy_data($list_event['event_id']);
                $fantacyData[$list_event['event_id']] =  $fancy_data;
            }
        }



        $postdata = json_encode($exchangeData);

        $url = 'http://365exchange.vip:3000/';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        $result = curl_exec($ch);
        curl_close($ch);

        sendfancyresponse($fantacyData);
        print_r($result);
    }


    public function favouriteEvent()
    {
        $event_id = $this->input->post('event_id');
        $user_id = $_SESSION['my_userdata']['user_id'];

        $dataArray = array(
            'event_id' => $event_id,
            'user_id' => $user_id
        );

        $this->Favourite_event_model->addFavouriteEvent($dataArray);
    }

    public function check_exists()
    {
        $this->Event_model->check_exists(array());
    }

    public function getPosition()
    {
        $user_id = get_user_id();
        $fancy_id = $this->input->post('fancyid');
        $type_id = $this->input->post('typeid');
        $yes_val = $this->input->post('yesval');
        $no_val = $this->input->post('noval');

        if (get_user_type() == 'Master') {
            $user_id = get_user_id();
            $user =  $this->User_model->getUserById($user_id);
            $partnership = $user->partnership;
            $users =  $this->User_model->getInnerUserById($user_id);

            $userArray = array();

            foreach ($users as $user) {
                $userArray[] = $user->user_id;
            }

            $dataArray = array(
                'selection_id' => $fancy_id,
                'users' => $userArray
            );

            $max = $this->Betting_model->get_max_fancy_bettings_by_users($dataArray);
            $min = $this->Betting_model->get_min_fancy_bettings_by_users($dataArray);
            $max_p = $max + 5;
            $min_p = $min - 5;

            $scores = array_reverse(range($min_p, $max_p));

            $bettings = $this->Betting_model->get_fancy_bettings_by_users($dataArray);

            $tmp_array = array();

            foreach ($bettings as $betting) {
                $price_val  = $betting->price_val;
                $stake  = $betting->stake;

                $profit  = $betting->profit;
                $loss  = $betting->loss;

                foreach ($scores as $score) {

                    if ($betting->is_back == 0) {


                        if (isset($tmp_array[$score])) {

                            if ($score >= $price_val) {
                                $loss_amt =  ($loss * 1) * $partnership / 100;

                                $total = $tmp_array[$score] + $loss_amt;

                                $tmp_array[$score] = $total;
                            } else {

                                $profit_amt =   ($profit * -1) * $partnership / 100;

                                $total = ($tmp_array[$score] + $profit_amt);
                                $tmp_array[$score] = $total;
                            }
                        } else {

                            if ($score >= $price_val) {
                                $tmp_array[$score] = ($loss * 1) * $partnership / 100;
                            } else {
                                $tmp_array[$score] = ($profit * -1) * $partnership / 100;
                            }
                        }
                    } else {

                        if (isset($tmp_array[$score])) {
                            if ($score >= $price_val) {

                                $profit_amt = ($profit * -1) * $partnership / 100;
                                $total = ($tmp_array[$score] + $profit_amt);
                                $tmp_array[$score] = $total;
                            } else {

                                $loss_amt = ($loss * 1) * $partnership / 100;

                                $total = ($tmp_array[$score] +  $loss_amt);
                                $tmp_array[$score] = $total;
                            }
                        } else {
                            if ($score < $price_val) {
                                $tmp_array[$score] = ($loss) * $partnership / 100;
                            } else {
                                $tmp_array[$score] = ($profit * -1) * $partnership / 100;
                            }
                        }
                    }
                }
            }

            $html = $this->load->viewPartial('get-positions', array('scores' => $tmp_array));

            echo $html;
        } else if (get_user_type() == 'Super Master') {

            $user_id = get_user_id();
            $user =  $this->User_model->getUserById($user_id);
            $partnership = $user->partnership;
            $masterUsers =  $this->User_model->getInnerUserById($user_id);
            $userArray = array();
            $partnerShipArray = array();


            if (!empty($masterUsers)) {
                foreach ($masterUsers as $masterUser) {
                    $users =  $this->User_model->getInnerUserById($masterUser->user_id);

                    if (!empty($users)) {
                        foreach ($users as $user) {
                            $userArray[] = $user->user_id;
                        }
                    }
                    $masterUserArray[] = $user->user_id;
                    $partnerShipArray[$user->user_id] = $user->partnership;
                }
            }


            $dataArray = array(
                'selection_id' => $fancy_id,
                'users' => $userArray
            );


            $max = $this->Betting_model->get_max_fancy_bettings_by_users($dataArray);
            $min = $this->Betting_model->get_min_fancy_bettings_by_users($dataArray);
            $max_p = $max + 5;
            $min_p = $min - 5;

            $scores = array_reverse(range($min_p, $max_p));

            $bettings = $this->Betting_model->get_fancy_bettings_by_users($dataArray);

            $tmp_array = array();

            foreach ($bettings as $betting) {
                $user_id = get_user_id();
                $user =  $this->User_model->getUserById($betting->user_id);
                $masterUser =  $this->User_model->getUserById($user->master_id);

                $masterUserPartnership = $masterUser->partnership;


                $price_val  = $betting->price_val;
                $stake  = $betting->stake;
                $profit  = $betting->profit;
                $loss  = $betting->loss;

                foreach ($scores as $score) {

                    if ($betting->is_back == 0) {


                        if (isset($tmp_array[$score])) {


                            if ($score >= $price_val) {
                                $loss_amt = ($loss * 1) * $partnership / 100;
                                $total = ($tmp_array[$score] + $loss_amt);
                                $master_loss_amt = ($loss * 1) * $masterUserPartnership / 100;
                                $masterUsertotal = ($tmp_array[$score] + $master_loss_amt);

                                $tmp_array[$score] = $total - $masterUsertotal;
                            } else {
                                $profit_amt =  ($profit * -1) * $partnership / 100;
                                $total = ($tmp_array[$score] + $profit_amt);

                                $master_profit_amt =  ($profit * -1)  * $masterUserPartnership / 100;

                                $masterUsertotal = ($tmp_array[$score] + $master_profit_amt);
                                $tmp_array[$score] = $total - $masterUsertotal;
                            }
                        } else {

                            if ($score >= $price_val) {

                                $total = ($loss * 1) * $partnership / 100;
                                $masterUsertotal = ($loss * 1)  * $masterUserPartnership / 100;

                                $tmp_array[$score] = $total - $masterUsertotal;
                            } else {
                                $total = ($profit * -1) * $partnership / 100;
                                $masterUsertotal = ($profit * -1)  * $masterUserPartnership / 100;

                                $tmp_array[$score] = $total - $masterUsertotal;
                            }
                        }
                    } else {
                        if (isset($tmp_array[$score])) {
                            if ($score >= $price_val) {
                                $profit_amt = ($profit * -1) * $partnership / 100;


                                $total = ($tmp_array[$score] + $profit_amt);

                                $master_profit_amt =  ($profit * -1)  * $masterUserPartnership / 100;
                                $masterUsertotal =  ($tmp_array[$score] + $master_profit_amt);
                                $tmp_array[$score] = $total - $masterUsertotal;
                            } else {
                                $loss_amt = ($loss * 1) * $partnership / 100;
                                $total = ($tmp_array[$score] + $loss_amt);

                                $master_loss_amt = ($loss * 1)  * $masterUserPartnership / 100;
                                $masterUsertotal =  ($tmp_array[$score] + $master_loss_amt);
                                $tmp_array[$score] = $total - $masterUsertotal;
                            }
                        } else {
                            if ($score < $price_val) {
                                $total = ($loss) * $partnership / 100;
                                $masterUsertotal = ($loss) * $masterUserPartnership / 100;
                                $tmp_array[$score] = $total - $masterUsertotal;
                            } else {
                                $total = ($profit * -1) * $partnership / 100;
                                $masterUsertotal = ($profit * -1)  * $masterUserPartnership / 100;


                                $tmp_array[$score] = $total - $masterUsertotal;
                            }
                        }
                    }
                }
            }

            $html = $this->load->viewPartial('get-positions', array('scores' => $tmp_array));

            echo $html;
        } else if (get_user_type() == 'Hyper Super Master') {

            $user_id = get_user_id();
            $user =  $this->User_model->getUserById($user_id);
            $partnership = $user->partnership;
            $superMasterUsers =  $this->User_model->getInnerUserById($user_id);
            $userArray = array();
            $partnerShipArray = array();

            if (!empty($superMasterUsers)) {
                foreach ($superMasterUsers as $superMasterUser) {
                    $masterUsers =  $this->User_model->getInnerUserById($superMasterUser->user_id);
                    if (!empty($masterUsers)) {
                        foreach ($masterUsers as $masterUser) {
                            $users =  $this->User_model->getInnerUserById($masterUser->user_id);

                            if (!empty($users)) {
                                foreach ($users as $user) {
                                    $userArray[] = $user->user_id;
                                }
                            }
                            $masterUserArray[] = $user->user_id;
                            $partnerShipArray[$user->user_id] = $user->partnership;
                        }
                    }


                    $dataArray = array(
                        'selection_id' => $fancy_id,
                        'users' => $userArray
                    );
                }
            }




            $max = $this->Betting_model->get_max_fancy_bettings_by_users($dataArray);
            $min = $this->Betting_model->get_min_fancy_bettings_by_users($dataArray);
            $max_p = $max + 5;
            $min_p = $min - 5;

            $scores = array_reverse(range($min_p, $max_p));

            $bettings = $this->Betting_model->get_fancy_bettings_by_users($dataArray);

            $tmp_array = array();

            foreach ($bettings as $betting) {
                $user_id = get_user_id();
                $user =  $this->User_model->getUserById($betting->user_id);
                $masterUser =  $this->User_model->getUserById($user->master_id);
                $superMasterUser =  $this->User_model->getUserById($masterUser->master_id);

                $masterUserPartnership = $superMasterUser->partnership;


                $price_val  = $betting->price_val;
                $stake  = $betting->stake;
                $profit  = $betting->profit;
                $loss  = $betting->loss;


                foreach ($scores as $score) {
                    if ($betting->is_back == 0) {


                        if (isset($tmp_array[$score])) {

                            if ($score >= $price_val) {

                                $total = ($tmp_array[$score] + $loss * 1) * $partnership / 100;
                                $masterUsertotal = ($tmp_array[$score] + $loss * 1) * $masterUserPartnership / 100;

                                $tmp_array[$score] = $total - $masterUsertotal;
                            } else {

                                $total = ($tmp_array[$score] + $profit * -1) * $partnership / 100;
                                $masterUsertotal = ($tmp_array[$score] + $profit * -1)  * $masterUserPartnership / 100;
                                $tmp_array[$score] = $total - $masterUsertotal;
                            }
                        } else {

                            if ($score >= $price_val) {

                                $total = ($loss * 1) * $partnership / 100;
                                $masterUsertotal = ($loss * 1)  * $masterUserPartnership / 100;

                                $tmp_array[$score] = $total - $masterUsertotal;
                            } else {
                                $total = ($profit * -1) * $partnership / 100;
                                $masterUsertotal = ($profit * -1)  * $masterUserPartnership / 100;

                                $tmp_array[$score] = $total - $masterUsertotal;
                            }
                        }
                    } else {

                        if (isset($tmp_array[$score])) {
                            if ($score >= $price_val) {
                                $total = ($tmp_array[$score] + $profit * -1) * $partnership / 100;
                                $masterUsertotal =  ($tmp_array[$score] + $profit * -1)  * $masterUserPartnership / 100;
                                // p($tmp_array[$score].'---'.$profit);
                                $tmp_array[$score] = $total - $masterUsertotal;
                            } else {
                                $total = ($tmp_array[$score] + $loss * 1) * $partnership / 100;
                                $masterUsertotal =  ($tmp_array[$score] + $loss * 1)  * $masterUserPartnership / 100;

                                $tmp_array[$score] = $total - $masterUsertotal;
                            }
                        } else {
                            if ($score < $price_val) {
                                $total = ($loss) * $partnership / 100;
                                $masterUsertotal = ($loss) * $masterUserPartnership / 100;
                                $tmp_array[$score] = $total - $masterUsertotal;
                            } else {
                                $total = ($profit * -1) * $partnership / 100;
                                $masterUsertotal = ($profit * -1)  * $masterUserPartnership / 100;


                                $tmp_array[$score] = $total - $masterUsertotal;
                            }
                        }
                    }
                }
            }

            $html = $this->load->viewPartial('get-positions', array('scores' => $tmp_array));

            echo $html;
        } else if (get_user_type() == 'Admin') {

            $user_id = get_user_id();
            $user =  $this->User_model->getUserById($user_id);
            $partnership = $user->partnership;
            $hyperSuperMasterUsers =  $this->User_model->getInnerUserById($user_id);
            $userArray = array();
            $partnerShipArray = array();

            if (!empty($hyperSuperMasterUsers)) {
                foreach ($hyperSuperMasterUsers as $hyperSuperMasterUser) {
                    $superMasterUsers =  $this->User_model->getInnerUserById($hyperSuperMasterUser->user_id);

                    if (!empty($superMasterUsers)) {
                        foreach ($superMasterUsers as $superMasterUser) {
                            $masterUsers =  $this->User_model->getInnerUserById($superMasterUser->user_id);
                            if (!empty($masterUsers)) {
                                foreach ($masterUsers as $masterUser) {
                                    $users =  $this->User_model->getInnerUserById($masterUser->user_id);

                                    if (!empty($users)) {
                                        foreach ($users as $user) {
                                            $userArray[] = $user->user_id;
                                        }
                                    }
                                    $masterUserArray[] = $user->user_id;
                                    $partnerShipArray[$user->user_id] = $user->partnership;
                                }
                            }


                            $dataArray = array(
                                'selection_id' => $fancy_id,
                                'users' => $userArray
                            );
                        }
                    }
                }
            }




            $max = $this->Betting_model->get_max_fancy_bettings_by_users($dataArray);
            $min = $this->Betting_model->get_min_fancy_bettings_by_users($dataArray);
            $max_p = $max + 5;
            $min_p = $min - 5;

            $scores = array_reverse(range($min_p, $max_p));

            $bettings = $this->Betting_model->get_fancy_bettings_by_users($dataArray);

            $tmp_array = array();

            foreach ($bettings as $betting) {
                $user_id = get_user_id();
                $user =  $this->User_model->getUserById($betting->user_id);
                $masterUser =  $this->User_model->getUserById($user->master_id);
                $superMasterUser =  $this->User_model->getUserById($masterUser->master_id);
                $hyperSuperMasterUser =  $this->User_model->getUserById($superMasterUser->master_id);


                $masterUserPartnership = $hyperSuperMasterUser->partnership;


                $price_val  = $betting->price_val;
                $stake  = $betting->stake;
                $profit  = $betting->profit;
                $loss  = $betting->loss;


                foreach ($scores as $score) {
                    if ($betting->is_back == 0) {


                        if (isset($tmp_array[$score])) {

                            if ($score >= $price_val) {

                                $total = ($tmp_array[$score] + $loss * 1) * $partnership / 100;
                                $masterUsertotal = ($tmp_array[$score] + $loss * 1) * $masterUserPartnership / 100;

                                $tmp_array[$score] = $total - $masterUsertotal;
                            } else {

                                $total = ($tmp_array[$score] + $profit * -1) * $partnership / 100;
                                $masterUsertotal = ($tmp_array[$score] + $profit * -1)  * $masterUserPartnership / 100;
                                $tmp_array[$score] = $total - $masterUsertotal;
                            }
                        } else {

                            if ($score >= $price_val) {

                                $total = ($loss * 1) * $partnership / 100;
                                $masterUsertotal = ($loss * 1)  * $masterUserPartnership / 100;

                                $tmp_array[$score] = $total - $masterUsertotal;
                            } else {
                                $total = ($profit * -1) * $partnership / 100;
                                $masterUsertotal = ($profit * -1)  * $masterUserPartnership / 100;

                                $tmp_array[$score] = $total - $masterUsertotal;
                            }
                        }
                    } else {

                        if (isset($tmp_array[$score])) {
                            if ($score >= $price_val) {
                                $total = ($tmp_array[$score] + $profit * -1) * $partnership / 100;
                                $masterUsertotal =  ($tmp_array[$score] + $profit * -1)  * $masterUserPartnership / 100;
                                // p($tmp_array[$score].'---'.$profit);
                                $tmp_array[$score] = $total - $masterUsertotal;
                            } else {
                                $total = ($tmp_array[$score] + $loss * 1) * $partnership / 100;
                                $masterUsertotal =  ($tmp_array[$score] + $loss * 1)  * $masterUserPartnership / 100;

                                $tmp_array[$score] = $total - $masterUsertotal;
                            }
                        } else {
                            if ($score < $price_val) {
                                $total = ($loss) * $partnership / 100;
                                $masterUsertotal = ($loss) * $masterUserPartnership / 100;
                                $tmp_array[$score] = $total - $masterUsertotal;
                            } else {
                                $total = ($profit * -1) * $partnership / 100;
                                $masterUsertotal = ($profit * -1)  * $masterUserPartnership / 100;


                                $tmp_array[$score] = $total - $masterUsertotal;
                            }
                        }
                    }
                }
            }

            $html = $this->load->viewPartial('get-positions', array('scores' => $tmp_array));

            echo $html;
        } else if (get_user_type() == 'Super Admin') {

            $user_id = get_user_id();
            $user =  $this->User_model->getUserById($user_id);
            $partnership = $user->partnership;
            $admiUsers =  $this->User_model->getInnerUserById($user_id);
            $userArray = array();
            $partnerShipArray = array();

            if (!empty($admiUsers)) {
                foreach ($admiUsers as $adminUser) {
                    $hyperSuperMasterUsers =  $this->User_model->getInnerUserById($adminUser->user_id);

                    if (!empty($hyperSuperMasterUsers)) {
                        foreach ($hyperSuperMasterUsers as $hyperSuperMasterUser) {
                            $superMasterUsers =  $this->User_model->getInnerUserById($hyperSuperMasterUser->user_id);

                            if (!empty($superMasterUsers)) {
                                foreach ($superMasterUsers as $superMasterUser) {
                                    $masterUsers =  $this->User_model->getInnerUserById($superMasterUser->user_id);
                                    if (!empty($masterUsers)) {
                                        foreach ($masterUsers as $masterUser) {
                                            $users =  $this->User_model->getInnerUserById($masterUser->user_id);

                                            if (!empty($users)) {
                                                foreach ($users as $user) {
                                                    $userArray[] = $user->user_id;
                                                }
                                            }
                                            $masterUserArray[] = $user->user_id;
                                            $partnerShipArray[$user->user_id] = $user->partnership;
                                        }
                                    }


                                    $dataArray = array(
                                        'selection_id' => $fancy_id,
                                        'users' => $userArray
                                    );
                                }
                            }
                        }
                    }
                }
            }





            $max = $this->Betting_model->get_max_fancy_bettings_by_users($dataArray);
            $min = $this->Betting_model->get_min_fancy_bettings_by_users($dataArray);
            $max_p = $max + 5;
            $min_p = $min - 5;

            $scores = array_reverse(range($min_p, $max_p));

            $bettings = $this->Betting_model->get_fancy_bettings_by_users($dataArray);

            $tmp_array = array();

            foreach ($bettings as $betting) {
                $user_id = get_user_id();
                $user =  $this->User_model->getUserById($betting->user_id);
                $masterUser =  $this->User_model->getUserById($user->master_id);
                $superMasterUser =  $this->User_model->getUserById($masterUser->master_id);
                $hyperSuperMasterUser =  $this->User_model->getUserById($superMasterUser->master_id);
                $adminUser =  $this->User_model->getUserById($hyperSuperMasterUser->master_id);


                $masterUserPartnership = $adminUser->partnership;


                $price_val  = $betting->price_val;
                $stake  = $betting->stake;
                $profit  = $betting->profit;
                $loss  = $betting->loss;

                foreach ($scores as $score) {
                    if ($betting->is_back == 0) {


                        if (isset($tmp_array[$score])) {

                            if ($score >= $price_val) {

                                $total = ($tmp_array[$score] + $loss * 1) * $partnership / 100;
                                $masterUsertotal = ($tmp_array[$score] + $loss * 1) * $masterUserPartnership / 100;

                                $tmp_array[$score] = $total - $masterUsertotal;
                            } else {

                                $total = ($tmp_array[$score] + $profit * -1) * $partnership / 100;
                                $masterUsertotal = ($tmp_array[$score] + $profit * -1)  * $masterUserPartnership / 100;
                                $tmp_array[$score] = $total - $masterUsertotal;
                            }
                        } else {

                            if ($score >= $price_val) {

                                $total = ($loss * 1) * $partnership / 100;
                                $masterUsertotal = ($loss * 1)  * $masterUserPartnership / 100;

                                $tmp_array[$score] = $total - $masterUsertotal;
                            } else {
                                $total = ($profit * -1) * $partnership / 100;
                                $masterUsertotal = ($profit * -1)  * $masterUserPartnership / 100;

                                $tmp_array[$score] = $total - $masterUsertotal;
                            }
                        }
                    } else {

                        if (isset($tmp_array[$score])) {
                            if ($score >= $price_val) {
                                $total = ($tmp_array[$score] + $profit * -1) * $partnership / 100;
                                $masterUsertotal =  ($tmp_array[$score] + $profit * -1)  * $masterUserPartnership / 100;
                                // p($tmp_array[$score].'---'.$profit);
                                $tmp_array[$score] = $total - $masterUsertotal;
                            } else {
                                $total = ($tmp_array[$score] + $loss * 1) * $partnership / 100;
                                $masterUsertotal =  ($tmp_array[$score] + $loss * 1)  * $masterUserPartnership / 100;

                                $tmp_array[$score] = $total - $masterUsertotal;
                            }
                        } else {
                            if ($score < $price_val) {
                                $total = ($loss) * $partnership / 100;
                                $masterUsertotal = ($loss) * $masterUserPartnership / 100;
                                $tmp_array[$score] = $total - $masterUsertotal;
                            } else {
                                $total = ($profit * -1) * $partnership / 100;
                                $masterUsertotal = ($profit * -1)  * $masterUserPartnership / 100;


                                $tmp_array[$score] = $total - $masterUsertotal;
                            }
                        }
                    }
                }
            }

            $html = $this->load->viewPartial('get-positions', array('scores' => $tmp_array));

            echo $html;
        } else {

            $dataArray = array(
                'selection_id' => $fancy_id,
                'user_id' => $user_id
            );

            $max = $this->Betting_model->get_max_fancy_bettings($dataArray);
            $min = $this->Betting_model->get_min_fancy_bettings($dataArray);

            $max_p = $max + 5;
            $min_p = $min - 5;

            $scores = array_reverse(range($min_p, $max_p));

            $bettings = $this->Betting_model->get_fancy_bettings($dataArray);

            $tmp_array = array();

            foreach ($bettings as $betting) {
                $price_val  = $betting->price_val;
                $stake  = $betting->stake;
                $profit  = $betting->profit;
                $loss  = $betting->loss;


                foreach ($scores as $score) {
                    if ($betting->is_back == 0) {
                        if (isset($tmp_array[$score])) {
                            if ($score >= $price_val) {
                                $total = $tmp_array[$score] + $loss * -1;


                                $tmp_array[$score] = $total;
                            } else {
                                $total = $tmp_array[$score] + $profit * 1;
                                $tmp_array[$score] = $total;
                            }
                        } else {
                            if ($score < $price_val) {
                                $tmp_array[$score] = $profit;
                            } else {
                                $tmp_array[$score] = $loss * -1;
                            }
                        }
                    } else {

                        if (isset($tmp_array[$score])) {
                            if ($score >= $price_val) {
                                $total = $tmp_array[$score] + $profit * 1;
                                $tmp_array[$score] = $total;
                            } else {
                                $total = $tmp_array[$score] + $loss * -1;
                                $tmp_array[$score] = $total;
                            }
                        } else {
                            if ($score >= $price_val) {
                                $tmp_array[$score] = $profit * 1;
                            } else {
                                $tmp_array[$score] = $loss * -1;
                            }
                        }
                    }
                }
            }

            $html = $this->load->viewPartial('get-positions', array('scores' => $tmp_array));

            echo $html;
        }
    }

    public function getExposure()
    {
        $user_id = get_user_id();
        $event_id = '30082209';
        $market_id = '1.174677254';

        $runners = $this->Event_model->get_market_book_odds_runner(array('market_id' => $market_id));

        $bettings = $this->Betting_model->get_bettings_by_market_id(array('market_id' => $market_id, 'user_id' => $user_id));

        if (!empty($runners)) {
            $selection_id_1 = $runners['0']['selection_id'];
            $selection_id_2 = $runners['1']['selection_id'];


            $tmp_betting = array(
                $selection_id_1 => array(
                    'profit' => 0,
                    'loss' => 0,

                ),
                $selection_id_2 => array(
                    'profit' => 0,
                    'loss' => 0,

                ),
            );
        }


        if (!empty($bettings)) {
            foreach ($bettings as $betting) {

                if (isset($tmp_betting[$betting->selection_id])) {
                    if ($betting->is_back == 1) {
                        $price = ($betting->price_val * $betting->stake * -1) + $betting->stake;;
                        $profit = $tmp_betting[$betting->selection_id]['profit']   += round($betting->stake);
                        $profit = $tmp_betting[$betting->selection_id]['loss']   += round($price);
                    } else {
                        $price = ($betting->price_val * $betting->stake * -1) + $betting->stake;;
                        $profit = $tmp_betting[$betting->selection_id]['profit']   += round($betting->stake);
                        $profit = $tmp_betting[$betting->selection_id]['loss']   += round($price);
                    }
                } else {

                    if ($betting->is_back == 1) {
                        $price = $betting->price_val * $betting->stake * -1;
                        $tmp_betting[$betting->selection_id] = $price;
                    } else {
                        $price = $betting->price_val * $betting->stake * 1;
                        $tmp_betting[$betting->selection_id] = $price;
                    }
                }
            }
        }

        $selection_id_1 = $runners['0']['selection_id'];
        $selection_id_2 = $runners['1']['selection_id'];

        $total_exposure = array(
            $selection_id_1 => 0,
            $selection_id_2 => 0,

        );

        $i = 0;

        foreach ($tmp_betting as $key => $tmp_bett) {

            if ($i == 0) {
                $total_exposure[$selection_id_1] += $tmp_bett['loss'] * -1;
                $total_exposure[$selection_id_2] += $tmp_bett['profit'] * -1;
            } else {
                $total_exposure[$selection_id_1] += $tmp_bett['profit'] * -1;
                $total_exposure[$selection_id_2] += $tmp_bett['loss'] * -1;
            }
            $i++;
        }
        echo json_encode($total_exposure);
    }


    public function getExpiredFancyData()
    {   

        p('ehllo');
        $market_types = $this->Event_model->getMarketTypeIds();

        if (!empty($market_types)) {
            foreach ($market_types as $market_type) {

                $listMarketBookSession = json_decode(listMarketBookSession($market_type['event_id']));

                if (!empty($listMarketBookSession)) {

                    if (!isset($listMarketBookSession->message)) {
                        foreach ($listMarketBookSession as $session) {
                            $dataArray = array(
                                'match_id' => $market_type['event_id'],
                                'selection_id' => $session->SelectionId,
                                'runner_name' => $session->RunnerName,
                                'lay_price1' => $session->LayPrice1,
                                'lay_size1' => $session->LaySize1,
                                'back_price1' => $session->BackPrice1,
                                'back_size1' => $session->BackSize1,
                                'game_status' => $session->GameStatus,
                                'mark_status' => $session->MarkStatus,

                            );
                            $this->Event_model->addMarketBookOddsFancy($dataArray);
                        }
                    }
                }
            }
        }
    }
}
