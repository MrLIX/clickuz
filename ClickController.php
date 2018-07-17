<?php

namespace app\controllers;


use app\components\ClickData;
use app\models\ClickTransactions;
use app\models\Orders;
use app\models\User;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

class ClickController extends Controller
{

    private $reqData = [];
    private $user; // init into validateData()
    private $userID = 5;


    public function beforeAction($action)
    {
        if ($action->id == "prepare" || $action->id == "complete") {
            $this->enableCsrfValidation = false;
        }
        return parent::beforeAction($action);
    }

    /**
     * @return string
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\base\NotSupportedException
     * @throws \yii\db\Exception
     * @throws \yii\db\StaleObjectException
     */
    public function actionPrepare()
    {
        $this->reqData = $_POST;
        $this->validateData();
        $checkExists = ClickTransactions::find()
            ->where(['click_trans_id' => $this->reqData['click_trans_id']])->one();

        if ($checkExists !== NULL) {
            if ($checkExists->status == ClickTransactions::STATUS_CANCEL) {
                //Transaction cancelled
                die(json_encode(ClickData::getMessage('9')));
            } //Already paid
            else die(json_encode(ClickData::getMessage('4')));
        }

        //Error in request from click
        if (!$this->reqData['error'] == 0) die(json_encode(ClickData::getMessage('8')));

        $newTransaction = new ClickTransactions;
        $newTransaction->user_id        = $this->reqData['merchant_trans_id'];
        $newTransaction->click_trans_id = $this->reqData['click_trans_id'];
        $newTransaction->service_id     = $this->reqData['service_id'];
        $newTransaction->amount         = $this->reqData['amount'];
        $newTransaction->sign_time      = $this->reqData['sign_time'];
        $newTransaction->click_paydoc_id = $this->reqData['click_paydoc_id'];
        $newTransaction->create_time    = time();
        $newTransaction->status         = ClickTransactions::STATUS_INACTIVE;

        if ($newTransaction->save(false)) {

            $merchant_prepare_id = $newTransaction->id;
            $return_array = array(
                'click_trans_id' => $this->reqData['click_trans_id'],        // ID Click Trans
                'merchant_trans_id' => $this->reqData['merchant_trans_id'],  // ID платежа в биллинге Поставщика
                'merchant_prepare_id' => $merchant_prepare_id                // ID платежа для подтверждения
            );

            $result = array_merge(ClickData::getMessage('0'), $return_array);

            die(json_encode($result));
        }
        // other case report: Unknown Error
        die(json_encode(1));
    }

    public function actionComplete()
    {
        $this->reqData = $_POST;

        //if not validated it is end point
        //-------------------------------------------
        $this->validateData();

        //-------------------------------------------
        //Error in request from click

        if (empty($this->reqData['merchant_prepare_id'])) die(json_encode(ClickData::getMessage('8')));


        // --------------------------------------------------------------------------- Start trasaction DB
        $transaction = ClickTransactions::findOne(
            [
                'id' => $this->reqData['merchant_prepare_id'],
                'user_id' => $this->reqData['merchant_trans_id'],
                'click_trans_id' => $this->reqData['click_trans_id'],
                'click_paydoc_id' => $this->reqData['click_paydoc_id'],
                'service_id' => $this->reqData['service_id'],
            ]
        );


        if ($transaction !== NULL) {

            if ($this->reqData['error'] == 0) {

                if ($this->reqData['amount'] == $transaction->amount) {

                    if ($transaction->status == ClickTransactions::STATUS_INACTIVE) {

                        $db = \Yii::$app->db;
                        $db_transaction = $db->beginTransaction();
                        $transaction->status = ClickTransactions::STATUS_ACTIVE;

                        if (!$transaction->save(false)) {
                            $db_transaction->rollback();
                            die(json_encode(ClickData::getMessage('n')));
                        }
                        $db_transaction->commit();

                        $order = Orders::findOne($transaction->user_id);        // if pay success -> Change Order status to 2
                        if(!empty($order)){
                            $order->state = 2;
                            $order->save(false);
                        }
                        $return_array = [
                            'click_trans_id' => $transaction->click_trans_id,
                            'merchant_trans_id' => $transaction->user_id,
                            'merchant_confirm_id' => $transaction->id,
                        ];

                        $result = array_merge(ClickData::getMessage('0'), $return_array);

                        die(json_encode($result));
                    } elseif ($transaction->status == ClickTransactions::STATUS_CANCEL) {
                        //"Transaction cancelled"
                        die(json_encode(ClickData::getMessage('9')));
                    } elseif ($transaction->status == ClickTransactions::STATUS_ACTIVE) {
                        die(json_encode(ClickData::getMessage('4')));
                    } else die(json_encode(ClickData::getMessage('n')));
                } else {
                    if ($transaction->status == ClickTransactions::STATUS_INACTIVE)
                        //$transaction->delete();
                        //"Incorrect parameter amount"
                        die(json_encode(ClickData::getMessage('2')));
                }
            } elseif ($this->reqData['error'] < 0) {

                if ($this->reqData['error'] == -5017) {           // "Transaction cancelled"



                    if ($transaction->status != ClickTransactions::STATUS_ACTIVE) {
                        $transaction->status = ClickTransactions::STATUS_CANCEL;
                        if ($transaction->save(false)) {
                            // "Transaction cancelled"
                            $this->send_mail_complete($this->reqData, true);
                            die(json_encode(ClickData::getMessage('9')));
                        }
                        die(json_encode(ClickData::getMessage('n')));
                    } else die(json_encode(ClickData::getMessage('n')));
                } elseif ($this->reqData['error'] == -1 && $transaction->status == ClickTransactions::STATUS_ACTIVE) {
                    die(json_encode(ClickData::getMessage('4')));
                } else die(json_encode(ClickData::getMessage('n')));

            } // error > 0
            else {
                die(json_encode(ClickData::getMessage('n')));
            }
        } // Transaction is null
        else {
            // Transaction does not exist
            die(json_encode(ClickData::getMessage('6')));
        }
//        echo "Hello from Complete // ";
        print_r(ClickData::getMessage(0));
        // var_dump(ClickData::$messages);
    }

    private function validateData()
    {
        //check complete parameters: Unknown Error
        if ((!isset($this->reqData['click_trans_id'])) ||
            (!isset($this->reqData['service_id'])) ||
            (!isset($this->reqData['click_paydoc_id'])) ||
            (!isset($this->reqData['merchant_trans_id'])) ||
            (!isset($this->reqData['amount'])) ||
            (!isset($this->reqData['action'])) ||
            (!isset($this->reqData['sign_time'])) ||
            (!isset($this->reqData['sign_string'])) ||
            (!isset($this->reqData['error']))
        ) {

            die(json_encode(ClickData::getMessage('n')));
        }

        // Формирование ХЭШ подписи
        $sign_string_veryfied = md5(
            $this->reqData['click_trans_id'] .
            $this->reqData['service_id'] .
            ClickData::SECRET_KEY .
            $this->reqData['merchant_trans_id'] .
            (($this->reqData['action'] == 1) ? $this->reqData['merchant_prepare_id'] : '') .
            $this->reqData['amount'] .
            $this->reqData['action'] .
            $this->reqData['sign_time']
        );

        if ($this->reqData['sign_string'] != $sign_string_veryfied) {
            die(json_encode(ClickData::getMessage('1')));
        }

        // Check Actions: Action not found
        if (!in_array($this->reqData['action'], [0, 1])) die(json_encode(ClickData::getMessage('3')));

        // Check sum: Incorrect parameter amount
        if (($this->reqData['amount'] < ClickData::MIN_AMOUNT) || ($this->reqData['amount'] > ClickData::MAX_AMOUNT)) {
            die(json_encode(ClickData::getMessage('2')));
        }

        //
        $this->user = User::findOne($this->reqData['merchant_trans_id']);
        if ($this->user === NULL) {
            // User does not exist
            die(json_encode(ClickData::getMessage('5')));
        }
    }

    private function send_mail_complete($data, $notcomplete = false)
    {
        if (!$notcomplete) {
            $message = <<<MESSAGE
                        <p>Message</p>
MESSAGE;
            $subject_text = 'Оплата CLICK';
        } else {
           $message = <<<MESSAGE
                        <p>Message</p>
MESSAGE;
            $subject_text = 'Отмена CLICK';
        }
        Yii::$app->mailer->compose()
            ->setFrom('')
            ->setTo([''])
            ->setSubject($subject_text)
            ->setHtmlBody($message)
            ->send();
    }

    /**
     * Lists all ClickTransactions models.
     * @return mixed
     */
    public function actionIndex()
    {

        return $this->render('click');
    }

}