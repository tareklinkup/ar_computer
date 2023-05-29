<?php
    class Transfer extends CI_Controller{
        public function __construct(){
            parent::__construct();
            $access = $this->session->userdata('userId');
            if($access == '' ){
                redirect("Login");
            }
            $this->load->model('Model_table', "mt", TRUE);
        }

        public function productTransfer(){
            $access = $this->mt->userAccess();
            if(!$access){
                redirect(base_url());
            }

            $data['transferId'] = 0;
            $data['title'] = "Product Transfer";
            $data['content'] = $this->load->view('Administrator/transfer/product_transfer', $data, TRUE);
            $this->load->view('Administrator/index', $data);
        }

        public function transferEdit($transferId){
            $access = $this->mt->userAccess();
            if(!$access){
                redirect(base_url());
            }

            $data['transferId'] = $transferId;
            $data['title'] = "Product Transfer";
            $data['content'] = $this->load->view('Administrator/transfer/product_transfer', $data, TRUE);
            $this->load->view('Administrator/index', $data);
        }

        public function addProductTransfer(){
            $res = ['success'=>false, 'message'=>''];
            try{
                $this->db->trans_begin();
                $data = json_decode($this->input->raw_input_stream);
                // echo json_encode($data);
                // return 0;
                $transfer = array(
                    'transfer_date' => $data->transfer->transfer_date,
                    'transfer_by' => $data->transfer->transfer_by,
                    'transfer_from' => $this->session->userdata('BRANCHid'),
                    'transfer_to' => $data->transfer->transfer_to,
                    'note' => $data->transfer->note,
                    'status' => 'p',
                    'total_amount' => $data->transfer->total_amount
                );

                $this->db->insert('tbl_transfermaster', $transfer);
                $transferId = $this->db->insert_id();

                foreach($data->cart as $cartProduct){
                    $transferDetails = array(
                        'transfer_id' => $transferId,
                        'product_id' => $cartProduct->product_id,
                        'quantity' => $cartProduct->quantity,
                        'purchase_rate' => $cartProduct->purchase_rate,
                        'total' => $cartProduct->total
                    );

                    $this->db->insert('tbl_transferdetails', $transferDetails);

                    $currentBranchInventoryCount = $this->db->query("select * from tbl_currentinventory where product_id = ? and branch_id = ?", [$cartProduct->product_id, $this->session->userdata('BRANCHid')])->num_rows();
                    if($currentBranchInventoryCount == 0){
                        $currentBranchInventory = array(
                            'product_id' => $cartProduct->product_id,
                            'transfer_from_quantity' => $cartProduct->quantity,
                            'branch_id' => $this->session->userdata('BRANCHid')
                        );

                        $this->db->insert('tbl_currentinventory', $currentBranchInventory);
                    } else {
                        $this->db->query("
                            update tbl_currentinventory
                            set transfer_from_quantity = transfer_from_quantity + ?
                            where product_id = ? 
                            and branch_id = ?
                        ", [$cartProduct->quantity, $cartProduct->product_id, $this->session->userdata('BRANCHid')]);
                    }

                    // $transferToBranchInventoryCount = $this->db->query("select * from tbl_currentinventory where product_id = ? and branch_id = ?", [$cartProduct->product_id, $data->transfer->transfer_to])->num_rows();
                    // if($transferToBranchInventoryCount == 0){
                    //     $transferToBranchInventory = array(
                    //         'product_id' => $cartProduct->product_id,
                    //         'transfer_to_quantity' => $cartProduct->quantity,
                    //         'branch_id' => $data->transfer->transfer_to
                    //     );

                    //     $this->db->insert('tbl_currentinventory', $transferToBranchInventory);
                    // } else {
                    //     $this->db->query("
                    //         update tbl_currentinventory
                    //         set transfer_to_quantity = transfer_to_quantity + ?
                    //         where product_id = ?
                    //         and branch_id = ?
                    //     ", [$cartProduct->quantity, $cartProduct->product_id, $data->transfer->transfer_to]);
                    // }

                    //update serial number
                    foreach($cartProduct->SerialStore as $value) {
                        $serial = array( 
                            'ps_brunch_id'=> $data->transfer->transfer_to,
                            'ps_transfer_from'=> $this->session->userdata('BRANCHid'),
                            'ps_transfer_to'=> $data->transfer->transfer_to
                        );
                        $this->db->where('ps_id', $value->ps_id)->update('tbl_product_serial_numbers', $serial);
                    }
                }
                if ($this->db->trans_status() === FALSE) {
                    $this->db->trans_rollback();
                } else {
                    $this->db->trans_commit();
                    $res = ['success'=>true, 'message'=>'Transfer success'];
                }
            } catch (Exception $ex){
                $this->db->trans_rollback();
                $res = ['success'=>false, 'message'=>$ex->getMessage];
            }

            echo json_encode($res);
        }
        public function approveProductTransfer() {

            $res = ['success'=>false, 'message'=>''];

            try{
                $this->db->trans_begin();
                $data = json_decode($this->input->raw_input_stream);

                $transferDetails = $this->db->query("
                select * from tbl_transferdetails where transfer_id = ? ", $data->transfer_id)->result();
                
                // echo json_encode($transferDetails);
                // return 0;
                foreach ($transferDetails as $key => $product) {
                    $transferToBranchInventoryCount = $this->db->query("select * from tbl_currentinventory where product_id = ? and branch_id = ?", [$product->product_id, $data->transfer_to])->num_rows();
                    if($transferToBranchInventoryCount == 0){
                        $transferToBranchInventory = array(
                            'product_id' => $product->product_id,
                            'transfer_to_quantity' => $product->quantity,
                            'branch_id' => $data->transfer_to
                        );

                        $this->db->insert('tbl_currentinventory', $transferToBranchInventory);
                    } else {
                        $this->db->query("
                            update tbl_currentinventory
                            set transfer_to_quantity = transfer_to_quantity + ?
                            where product_id = ?
                            and branch_id = ?
                        ", [$product->quantity, $product->product_id, $data->transfer_to]);
                    }
                }

                $this->db->query("
                    update tbl_transfermaster
                    set status = 'a'
                    where transfer_id = ? 
                ", $data->transfer_id);

                if ($this->db->trans_status() === FALSE) {
                    $this->db->trans_rollback();
                } else {
                    $this->db->trans_commit();
                    $res = ['success'=>true, 'message'=>'Approve success'];
                }
            } catch (Exception $ex){
                $this->db->trans_rollback();
                $res = ['success'=>false, 'message'=>$ex->getMessage];
            }
            echo json_encode($res);
        }
        public function updateProductTransfer(){
            $res = ['success'=>false, 'message'=>''];
            try{
                $this->db->trans_begin();
                $data           = json_decode($this->input->raw_input_stream);
                $transferId     =   $data->transfer->transfer_id;

                $oldTransfer    =   $this->db->query("select * from tbl_transfermaster where transfer_id = ?", $transferId)->row();

                $transfer = array(
                    'transfer_date' => $data->transfer->transfer_date,
                    'transfer_by' => $data->transfer->transfer_by,
                    'transfer_from' => $this->session->userdata('BRANCHid'),
                    'transfer_to' => $data->transfer->transfer_to,
                    'status' => 'p',
                    'note' => $data->transfer->note
                );

                $this->db->where('transfer_id', $transferId)->update('tbl_transfermaster', $transfer);

                $oldTransferDetails = $this->db->query("select * from tbl_transferdetails where transfer_id = ?", $transferId)->result();
                $this->db->query("delete from tbl_transferdetails where transfer_id = ?", $transferId);
                foreach($oldTransferDetails as $oldDetails) {
                    $this->db->query("
                        update tbl_currentinventory 
                        set transfer_from_quantity = transfer_from_quantity - ? 
                        where product_id = ?
                        and branch_id = ?
                    ", [$oldDetails->quantity, $oldDetails->product_id, $this->session->userdata('BRANCHid')]);

                    // $this->db->query("
                    //     update tbl_currentinventory 
                    //     set transfer_to_quantity = transfer_to_quantity - ? 
                    //     where product_id = ?
                    //     and branch_id = ?
                    // ", [$oldDetails->quantity, $oldDetails->product_id, $oldTransfer->transfer_to]);
                }

                foreach($data->cart as $cartProduct){
                    $transferDetails = array(
                        'transfer_id' => $transferId,
                        'product_id' => $cartProduct->product_id,
                        'quantity' => $cartProduct->quantity,
                        'purchase_rate' => $cartProduct->purchase_rate
                    );

                    $this->db->insert('tbl_transferdetails', $transferDetails);

                    $currentBranchInventoryCount = $this->db->query("select * from tbl_currentinventory where product_id = ? and branch_id = ?", [$cartProduct->product_id, $this->session->userdata('BRANCHid')])->num_rows();
                    if($currentBranchInventoryCount == 0){
                        $currentBranchInventory = array(
                            'product_id' => $cartProduct->product_id,
                            'transfer_from_quantity' => $cartProduct->quantity,
                            'branch_id' => $this->session->userdata('BRANCHid')
                        );

                        $this->db->insert('tbl_currentinventory', $currentBranchInventory);
                    } else {
                        $this->db->query("
                            update tbl_currentinventory 
                            set transfer_from_quantity = transfer_from_quantity + ? 
                            where product_id = ? 
                            and branch_id = ?
                        ", [$cartProduct->quantity, $cartProduct->product_id, $this->session->userdata('BRANCHid')]);
                    }

                    // $transferToBranchInventoryCount = $this->db->query("select * from tbl_currentinventory where product_id = ? and branch_id = ?", [$cartProduct->product_id, $data->transfer->transfer_to])->num_rows();
                    // if($transferToBranchInventoryCount == 0){
                    //     $transferToBranchInventory = array(
                    //         'product_id' => $cartProduct->product_id,
                    //         'transfer_to_quantity' => $cartProduct->quantity,
                    //         'branch_id' => $data->transfer->transfer_to
                    //     );

                    //     $this->db->insert('tbl_currentinventory', $transferToBranchInventory);
                    // } else {
                    //     $this->db->query("
                    //         update tbl_currentinventory
                    //         set transfer_to_quantity = transfer_to_quantity + ?
                    //         where product_id = ?
                    //         and branch_id = ?
                    //     ", [$cartProduct->quantity, $cartProduct->product_id, $data->transfer->transfer_to]);
                    // }

                    //update serial number
                    foreach($cartProduct->SerialStore as $value) {
                        $serial = array( 
                            'ps_brunch_id'=> $data->transfer->transfer_to,
                            'ps_transfer_from'=> $this->session->userdata('BRANCHid'),
                            'ps_transfer_to'=> $data->transfer->transfer_to
                        );
                        $this->db->where('ps_id', $value->ps_id)->update('tbl_product_serial_numbers', $serial);
                    }
                }

                if ($this->db->trans_status() === FALSE) {
                    $this->db->trans_rollback();
                } else {
                    $this->db->trans_commit();
                    $res = ['success'=>true, 'message'=>'Transfer updated'];
                }
            } catch (Exception $ex){
                $this->db->trans_rollback();
                $res = ['success'=>false, 'message'=>$ex->getMessage];
            }

            echo json_encode($res);
        }

        public function pendingTransferList(){
            $access = $this->mt->userAccess();
            if(!$access){
                redirect(base_url());
            }
            $data['title'] = "Pending Transfer List";
            $data['content'] = $this->load->view('Administrator/transfer/pending_transfer_list', $data, true);
            $this->load->view('Administrator/index', $data);
        }

        public function transferList(){
            $access = $this->mt->userAccess();
            if(!$access){
                redirect(base_url());
            }
            $data['title'] = "Transfer List";
            $data['content'] = $this->load->view('Administrator/transfer/transfer_list', $data, true);
            $this->load->view('Administrator/index', $data);
        }

        public function receivedList(){
            $access = $this->mt->userAccess();
            if(!$access){
                redirect(base_url());
            }
            $data['title'] = "Received List";
            $data['content'] = $this->load->view('Administrator/transfer/received_list', $data, true);
            $this->load->view('Administrator/index', $data);
        }

        public function pendingReceiveList(){
            $access = $this->mt->userAccess();
            if(!$access){
                redirect(base_url());
            }
            $data['title'] = "Pending Received List";
            $data['content'] = $this->load->view('Administrator/transfer/pending_received_list', $data, true);
            $this->load->view('Administrator/index', $data);
        }

        public function getTransfers(){
            $data = json_decode($this->input->raw_input_stream);

            $clauses = "";
            if(isset($data->branch) && $data->branch != ''){
                $clauses .= " and tm.transfer_to = '$data->branch'";
            }
            if(isset($data->status) && $data->status != ''){
                $clauses .= " and tm.status = '$data->status'";
            }
            if((isset($data->dateFrom) && $data->dateFrom != '') && (isset($data->dateTo) && $data->dateTo != '')){
                $clauses .= " and tm.transfer_date between '$data->dateFrom' and '$data->dateTo'";
            }

            if(isset($data->transferId) && $data->transferId != ''){
                $clauses .= " and tm.transfer_id = '$data->transferId'";
            }

            $transfers = $this->db->query("
                select
                    tm.*,
                    b.Brunch_name as transfer_to_name,
                    e.Employee_Name as transfer_by_name,
                    concat(tm.transfer_id, '-', b.Brunch_name, ' - ', tm.transfer_date) as display_name
                from tbl_transfermaster tm
                join tbl_brunch b on b.brunch_id = tm.transfer_to
                join tbl_employee e on e.Employee_SlNo = tm.transfer_by
                where tm.transfer_from = ?  $clauses
                order by tm.transfer_date desc
            ", $this->session->userdata('BRANCHid'))->result();

            echo json_encode($transfers);
        }

        public function getTransferDetails() {
            $data = json_decode($this->input->raw_input_stream);
            $transferDetails = $this->db->query("
                select 
                    td.*,
                    tm.transfer_to,
                    p.Product_Code,
                    p.Product_Name,
                    pc.ProductCategory_Name
                from tbl_transferdetails td
                join tbl_transfermaster tm on tm.transfer_id = td.transfer_id
                join tbl_product p on p.Product_SlNo = td.product_id
                left join tbl_productcategory pc on pc.ProductCategory_SlNo = p.ProductCategory_ID
                where td.transfer_id = ?
            ", $data->transferId)->result();

            $transferDetails = array_map(function($product) {
                $product->serials = $this->db->query("
                    select 
                        ps.*
                    from tbl_product_serial_numbers ps 
                    where ps.ps_status = 'a'
                    and ps.ps_prod_id = ?
                    and ps.ps_transfer_to = ?
                ", [$product->product_id, $product->transfer_to])->result();
                return $product;
            }, $transferDetails);            

            echo json_encode($transferDetails);
        }

        public function getReceives(){
            $data = json_decode($this->input->raw_input_stream);

            $branchClause = "";
            if($data->branch != null && $data->branch != ''){
                $branchClause = " and tm.transfer_from = '$data->branch'";
            }

            $dateClause = "";
            if(($data->dateFrom != null && $data->dateFrom != '') && ($data->dateTo != null && $data->dateTo != '')){
                $dateClause = " and tm.transfer_date between '$data->dateFrom' and '$data->dateTo'";
            }


            $transfers = $this->db->query("
                select
                    tm.*,
                    b.Brunch_name as transfer_from_name,
                    e.Employee_Name as transfer_by_name
                from tbl_transfermaster tm
                join tbl_brunch b on b.brunch_id = tm.transfer_from
                join tbl_employee e on e.Employee_SlNo = tm.transfer_by
                where tm.transfer_to = ? and tm.status = 'a' $branchClause $dateClause
            ", $this->session->userdata('BRANCHid'))->result();

            echo json_encode($transfers);
        }

        public function getPendingReceives(){
            $data = json_decode($this->input->raw_input_stream);

            $branchClause = "";
            if($data->branch != null && $data->branch != ''){
                $branchClause = " and tm.transfer_from = '$data->branch'";
            }

            $dateClause = "";
            if(($data->dateFrom != null && $data->dateFrom != '') && ($data->dateTo != null && $data->dateTo != '')){
                $dateClause = " and tm.transfer_date between '$data->dateFrom' and '$data->dateTo'";
            }


            $transfers = $this->db->query("
                select
                    tm.*,
                    b.Brunch_name as transfer_from_name,
                    e.Employee_Name as transfer_by_name
                from tbl_transfermaster tm
                join tbl_brunch b on b.brunch_id = tm.transfer_from
                join tbl_employee e on e.Employee_SlNo = tm.transfer_by
                where tm.transfer_to = ? and tm.status = 'p' $branchClause $dateClause
            ", $this->session->userdata('BRANCHid'))->result();

            echo json_encode($transfers);
        }

        public function transferInvoice($transferId){
            $data['title'] = 'Transfer Invoice';

            $data['transfer'] = $this->db->query("
                select
                    tm.*,
                    b.Brunch_name as transfer_to_name,
                    e.Employee_Name as transfer_by_name
                from tbl_transfermaster tm
                join tbl_brunch b on b.brunch_id = tm.transfer_to
                join tbl_employee e on e.Employee_SlNo = tm.transfer_by
                where tm.transfer_id = ?
            ", $transferId)->row();

            $data['transferDetails'] = $this->db->query("
                select
                    td.*,
                    p.Product_Code,
                    p.Product_Name,
                    pc.ProductCategory_Name
                from tbl_transferdetails td
                join tbl_product p on p.Product_SlNo = td.product_id
                join tbl_productcategory pc on pc.ProductCategory_SlNo = p.ProductCategory_ID
                where td.transfer_id = ?
            ", $transferId)->result();

            $data['content'] = $this->load->view('Administrator/transfer/transfer_invoice', $data, true);
            $this->load->view('Administrator/index', $data);
        }

        public function deletePendingTransfer() {
            $res = ['success'=>false, 'message'=>''];
            try{
                $data = json_decode($this->input->raw_input_stream);
                $transferId = $data->transferId;

                $oldTransfer = $this->db->query("select * from tbl_transfermaster where transfer_id = ?", $transferId)->row();
                $oldTransferDetails = $this->db->query("select * from tbl_transferdetails where transfer_id = ?", $transferId)->result();

                $this->db->query("delete from tbl_transfermaster where transfer_id = ?", $transferId);
                $this->db->query("delete from tbl_transferdetails where transfer_id = ?", $transferId);
                foreach($oldTransferDetails as $oldDetails) {
                    $this->db->query("
                        update tbl_currentinventory 
                        set transfer_from_quantity = transfer_from_quantity - ? 
                        where product_id = ?
                        and branch_id = ?
                    ", [$oldDetails->quantity, $oldDetails->product_id, $this->session->userdata('BRANCHid')]);

                    // $this->db->query("
                    //     update tbl_currentinventory 
                    //     set transfer_to_quantity = transfer_to_quantity - ? 
                    //     where product_id = ?
                    //     and branch_id = ?
                    // ", [$oldDetails->quantity, $oldDetails->product_id, $oldTransfer->transfer_to]);
                }

                $res = ['success'=>true, 'message'=>'Transfer deleted'];
            } catch (Exception $ex){
                $res = ['success'=>false, 'message'=>$ex->getMessage];
            }

            echo json_encode($res);
        }


        public function deleteTransfer() {
            $res = ['success'=>false, 'message'=>''];
            try{
                $data = json_decode($this->input->raw_input_stream);
                $transferId = $data->transferId;

                $oldTransfer = $this->db->query("select * from tbl_transfermaster where transfer_id = ?", $transferId)->row();
                $oldTransferDetails = $this->db->query("select * from tbl_transferdetails where transfer_id = ?", $transferId)->result();

                $this->db->query("delete from tbl_transfermaster where transfer_id = ?", $transferId);
                $this->db->query("delete from tbl_transferdetails where transfer_id = ?", $transferId);
                foreach($oldTransferDetails as $oldDetails) {
                    $this->db->query("
                        update tbl_currentinventory 
                        set transfer_from_quantity = transfer_from_quantity - ? 
                        where product_id = ?
                        and branch_id = ?
                    ", [$oldDetails->quantity, $oldDetails->product_id, $this->session->userdata('BRANCHid')]);

                    $this->db->query("
                        update tbl_currentinventory 
                        set transfer_to_quantity = transfer_to_quantity - ? 
                        where product_id = ?
                        and branch_id = ?
                    ", [$oldDetails->quantity, $oldDetails->product_id, $oldTransfer->transfer_to]);
                }

                $res = ['success'=>true, 'message'=>'Transfer deleted'];
            } catch (Exception $ex){
                $res = ['success'=>false, 'message'=>$ex->getMessage];
            }

            echo json_encode($res);
        }


        public function transferPaymentPage()  {
            $access = $this->mt->userAccess();
            if(!$access){
                redirect(base_url());
            }
            $data['title'] = "Transfer Payment";
            $data['content'] = $this->load->view('Administrator/due_report/transferPaymentPage', $data, TRUE);
            $this->load->view('Administrator/index', $data);
        }


        public function addTransferPayment() {
            $res = ['success'=>false, 'message'=>''];
            try{
                $paymentObj = json_decode($this->input->raw_input_stream);
                $payment = (array)$paymentObj;
                $payment['TPayment_invoice'] = $this->mt->generateTransferPaymentCode();
                $payment['TPayment_status'] = 'a';
                $payment['TPayment_Addby'] = $this->session->userdata("FullName");
                $payment['TPayment_AddDAte'] = date('Y-m-d H:i:s');
                $payment['TPayment_brunchid'] = $this->session->userdata("BRANCHid");
    
                $this->db->insert('tbl_transfer_payment', $payment);
                $paymentId = $this->db->insert_id();
                
                $res = ['success'=>true, 'message'=>'Payment added successfully', 'paymentId'=>$paymentId];
            } catch (Exception $ex){
                $res = ['success'=>false, 'message'=>$ex->getMessage()];
            }
            echo json_encode($res);
        }

        public function updateTransferPayment() {
            $res = ['success'=>false, 'message'=>''];
            try{
                $paymentObj = json_decode($this->input->raw_input_stream);
                $paymentId = $paymentObj->TPayment_id;
        
                $payment = (array)$paymentObj;
                unset($payment['TPayment_id']);
                $payment['update_by'] = $this->session->userdata("FullName");
                $payment['TPayment_UpdateDAte'] = date('Y-m-d H:i:s');
    
                $this->db->where('TPayment_id', $paymentObj->TPayment_id)->update('tbl_transfer_payment', $payment);
                
                $res = ['success'=>true, 'message'=>'Payment updated successfully', 'paymentId'=>$paymentId];
            } catch (Exception $ex){
                $res = ['success'=>false, 'message'=>$ex->getMessage()];
            }
    
            echo json_encode($res);
        }

        
        public function deleteTransferPayment(){
            $res = ['success'=>false, 'message'=>''];
            try{
                $data = json_decode($this->input->raw_input_stream);
        
                $this->db->set(['TPayment_status'=>'d'])->where('TPayment_id', $data->paymentId)->update('tbl_transfer_payment');
                
                $res = ['success'=>true, 'message'=>'Payment deleted successfully'];
            } catch (Exception $ex){
                $res = ['success'=>false, 'message'=>$ex->getMessage()];
            }

            echo json_encode($res);
        }


        public function getTransferPayments(){
            $data = json_decode($this->input->raw_input_stream);
    
            $paymentTypeClause = "";
            if(isset($data->paymentType) && $data->paymentType != '' && $data->paymentType == 'received'){
                $paymentTypeClause = " and tp.TPayment_TransactionType = 'CR'";
            }
            if(isset($data->paymentType) && $data->paymentType != '' && $data->paymentType == 'paid'){
                $paymentTypeClause = " and tp.TPayment_TransactionType = 'CP'";
            }
    
            $dateClause = "";
            if(isset($data->dateFrom) && $data->dateFrom != '' && isset($data->dateTo) && $data->dateTo != ''){
                $dateClause = " and tp.TPayment_date between '$data->dateFrom' and '$data->dateTo'";
            }
    
            $payments = $this->db->query("
                select
                    tp.*,
                    br.Brunch_title,
                    br.Brunch_name,
                    ba.account_name,
                    ba.account_number,
                    ba.bank_name,
                    case tp.TPayment_TransactionType
                    when 'CR' then 'Received'
                        when 'CP' then 'Paid'
                    end as transaction_type,
                    case tp.TPayment_Paymentby
                        when 'bank' then concat('Bank - ', ba.account_name, ' - ', ba.account_number, ' - ', ba.bank_name)
                        else 'Cash'
                    end as payment_by
                from tbl_transfer_payment tp
                left join tbl_bank_accounts ba on ba.account_id = tp.account_id
                join tbl_brunch br on br.brunch_id = tp.TPayment_customerID
                where tp.TPayment_status = 'a'
                and tp.TPayment_brunchid = ? $paymentTypeClause $dateClause
                order by tp.TPayment_id desc
            ", $this->session->userdata('BRANCHid'))->result();
    
            echo json_encode($payments);
        }

        public function getSpecialBranchesDue() {

            $data = json_decode($this->input->raw_input_stream);
           
            $clauses = "";
            if(isset($data->customerBranchId) && $data->customerBranchId != '') {
                $clauses = " and br.brunch_id = '$data->customerBranchId'";
            }
            if(isset($data->customerId) && $data->customerId != null){
                $clauses .= " and c.Customer_SlNo = '$data->customerId'";
            }
            if(isset($data->districtId) && $data->districtId != null){
                $clauses .= " and c.area_ID = '$data->districtId'";
            }

            $branchId = $this->session->userdata('BRANCHid');
        
            $dueResult = $this->db->query("
                select
                br.brunch_id,
                br.Brunch_name,
                br.Brunch_title,

                (select ifnull(sum(tm.total_amount), 0)
                    from tbl_transfermaster tm
                    where tm.transfer_to = br.brunch_id and tm.transfer_from = '$branchId'
                    and tm.status = 'a'
                ) as transferAmount,
    
                (select ifnull(sum(tp.TPayment_amount), 0.00) 
                    from tbl_transfer_payment tp 
                    where tp.TPayment_customerID = br.brunch_id and tp.TPayment_TransactionType = 'CR'
                    and tp.TPayment_status = 'a' and tp.TPayment_brunchid = '$branchId'
                ) as transferReceived,

                (select ifnull(sum(tp.TPayment_amount), 0.00) 
                    from tbl_transfer_payment tp 
                    where tp.TPayment_customerID = br.brunch_id 
                    and tp.TPayment_TransactionType = 'CP'
                    and tp.TPayment_status = 'a' and tp.TPayment_brunchid = '$branchId'
                ) as transferPaid,

                (select transferAmount - (transferReceived - transferPaid)) as dueAmount
                
                from tbl_brunch br
                where br.status = 'a'
                $clauses
            ")->result();

            echo json_encode($dueResult);
        }
    
         // customer combine ledger



        public function transferLedger() {
            $access = $this->mt->userAccess();
            if(!$access){
                redirect(base_url());
            }
            $data['title'] = "Transfer Ledger Reports";
            $branch_id = $this->session->userdata('BRANCHid');
    
            $data['content'] = $this->load->view('Administrator/payment_reports/transfer_payment_report', $data, TRUE);
            $this->load->view('Administrator/index', $data);
        }

        public function getTransferLedger() {
            $data = json_decode($this->input->raw_input_stream);
            // echo json_encode($data);
            // return 0;

            // $data->branchId
            // $previousDueQuery = $this->db->query("select ifnull(previous_due, 0.00) as previous_due from tbl_customer where Customer_SlNo = '$data->customerId'")->row();
            $currentBranchID = $this->session->userdata('BRANCHid');

            $payments = $this->db->query("
                select
                    'a' as sequence,
                    tm.transfer_date as date,
                    concat('Transfer -', tm.transfer_id) as description,
                    tm.total_amount as transfer_total,
                    0.00 as transfer_receive,
                    0.00 as transfer_payment,
                    0.00 as balance
                from tbl_transfermaster tm
                join tbl_brunch br on br.brunch_id = tm.transfer_from
                where tm.status = 'a' 
                and tm.transfer_to = '$data->branchId'
                and br.brunch_id = $currentBranchID

                UNION

                select 
                    'b' as sequence,
                    tp.TPayment_date as date,
                    concat('Transfer Receive -', tp.TPayment_invoice) as description,
                    0.00 as transfer_total,
                    tp.TPayment_amount as transfer_receive,
                    0.00 as transfer_payment,
                    0.00 as balance
                from tbl_transfer_payment tp
                join tbl_brunch br on br.brunch_id = tp.TPayment_brunchid
                where tp.TPayment_status = 'a' 
                and tp.TPayment_customerID = '$data->branchId'
                and tp.TPayment_TransactionType = 'CR'
                and br.brunch_id = $currentBranchID

                UNION

                select 
                    'c' as sequence,
                    tp.TPayment_date as date,
                    concat('Transfer Payment-', tp.TPayment_invoice) as description,
                    0.00 as transfer_total,
                    0.00 as transfer_receive,
                    tp.TPayment_amount as transfer_payment,
                    0.00 as balance
                from tbl_transfer_payment tp
                join tbl_brunch br on br.brunch_id = tp.TPayment_brunchid
                where tp.TPayment_status = 'a' 
                and tp.TPayment_customerID = '$data->branchId'
                and tp.TPayment_TransactionType = 'CP'
                and br.brunch_id = $currentBranchID
                order by date, sequence
            
            ")->result();

            $previousBalance = 0;
            
            foreach($payments as $key=>$payment) {
                $previousBalance = $payment->transfer_total;
                $lastBalance = $key == 0 ? 0 : $payments[$key - 1]->balance;
                $payment->balance = ($lastBalance + $payment->transfer_total + $payment->transfer_payment ) - ($payment->transfer_receive);
            }

            if((isset($data->dateFrom) && $data->dateFrom != null) && (isset($data->dateTo) && $data->dateTo != null)){
                $previousPayments = array_filter($payments, function($payment) use ($data){
                    return $payment->date < $data->dateFrom;
                });

                $previousBalance = count($previousPayments) > 0 ? $previousPayments[count($previousPayments) - 1]->balance : $previousBalance;

                $payments = array_filter($payments, function($payment) use ($data){
                    return $payment->date >= $data->dateFrom && $payment->date <= $data->dateTo;
                });

                $payments = array_values($payments);
            }

            $res['previousBalance'] = $previousBalance;
            $res['payments'] = $payments;
            echo json_encode($res);
        }
}