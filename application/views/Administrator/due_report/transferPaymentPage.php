<style>
	.v-select{
		margin-bottom: 5px;
	}
	.v-select.open .dropdown-toggle{
		border-bottom: 1px solid #ccc;
	}
	.v-select .dropdown-toggle{
		padding: 0px;
		height: 25px;
	}
	.v-select input[type=search], .v-select input[type=search]:focus{
		margin: 0px;
	}
	.v-select .vs__selected-options{
		overflow: hidden;
		flex-wrap:nowrap;
	}
	.v-select .selected-tag{
		margin: 2px 0px;
		white-space: nowrap;
		position:absolute;
		left: 0px;
	}
	.v-select .vs__actions{
		margin-top:-5px;
	}
	.v-select .dropdown-menu{
		width: auto;
		overflow-y:auto;
	}
	#transferPayment label{
		font-size:13px;
	}
	#transferPayment select{
		border-radius: 3px;
		padding: 0;
	}
	#transferPayment .add-button{
		padding: 2.5px;
		width: 28px;
		background-color: #298db4;
		display:block;
		text-align: center;
		color: white;
	}
	#transferPayment .add-button:hover{
		background-color: #41add6;
		color: white;
	}
</style>
<div id="transferPayment">
	<div class="row" style="border-bottom: 1px solid #ccc;padding-bottom: 15px;margin-bottom: 15px;">
		<div class="col-md-12">
			<form @submit.prevent="saveTransferPayment">
				<div class="row">
					<div class="col-md-5 col-md-offset-1">
						<div class="form-group">
							<label class="col-md-4 control-label">Transaction Type</label>
							<label class="col-md-1">:</label>
							<div class="col-md-7">
								<select class="form-control" v-model="payment.TPayment_TransactionType" required>
									<option value=""></option>
									<option value="CP">Payment</option>
									<option value="CR">Receive</option>
								</select>
							</div>
						</div>
						<div class="form-group">
							<label class="col-md-4 control-label">Payment Type</label>
							<label class="col-md-1">:</label>
							<div class="col-md-7">
								<select class="form-control" v-model="payment.TPayment_Paymentby" required>
									<option value="cash">Cash</option>
									<option value="bank">Bank</option>
								</select>
							</div>
						</div>
						<div class="form-group" style="display:none;" v-bind:style="{display: payment.TPayment_Paymentby == 'bank' ? '' : 'none'}">
							<label class="col-md-4 control-label">Bank Account</label>
							<label class="col-md-1">:</label>
							<div class="col-md-7">
								<v-select v-bind:options="filteredAccounts" v-model="selectedAccount" label="display_text" placeholder="Select account"></v-select>
							</div>
						</div>
						<div class="form-group">
							<label class="col-md-4 control-label">Branch</label>
							<label class="col-md-1">:</label>
							<div class="col-md-7">
								<select class="form-control" v-bind:style="{display: branches.length > 0 ? 'none' : ''}"></select>
								<v-select v-bind:options="branches" v-model="selectedBranch" label="Brunch_name" v-bind:style="{display: branches.length > 0 ? '' : 'none'}" @input="getSpecialBranchDue"></v-select>
							</div>
						</div>
						<!-- <div class="form-group">
							<label class="col-md-4 control-label">Transfer Invoice</label>
							<label class="col-md-1">:</label>
							<div class="col-md-7 col-xs-12">
								<select class="form-control" v-if="transfers.length == 0"></select>
								<v-select v-bind:options="transfers" v-model="selectedTransfer" label="display_name" @input="getTransferInvoiceDue" v-if="transfers.length > 0"></v-select>
							</div>
							
						</div> -->
						<div class="form-group">
							<label class="col-md-4 control-label">Due</label>
							<label class="col-md-1">:</label>
							<div class="col-md-7">
								<input type="text" class="form-control" v-model="transferDue" disabled>
							</div>
						</div>
					</div>

					<div class="col-md-5">
						<div class="form-group">
							<label class="col-md-4 control-label">Payment Date</label>
							<label class="col-md-1">:</label>
							<div class="col-md-7">
								<input type="date" class="form-control" v-model="payment.TPayment_date" required @change="getTransferPayments" v-bind:disabled="userType == 'u' ? true : false">
							</div>
						</div>
						<div class="form-group">
							<label class="col-md-4 control-label">Description</label>
							<label class="col-md-1">:</label>
							<div class="col-md-7">
								<input type="text" class="form-control" v-model="payment.TPayment_notes">
							</div>
						</div>
						<div class="form-group">
							<label class="col-md-4 control-label">Amount</label>
							<label class="col-md-1">:</label>
							<div class="col-md-7">
								<input type="number" class="form-control" v-model="payment.TPayment_amount" required>
							</div>
						</div>
						<div class="form-group">
							<div class="col-md-7 col-md-offset-5">
								<input type="submit" class="btn btn-success btn-sm" value="Save">
								<input type="button" class="btn btn-danger btn-sm" value="Cancel" @click="resetForm">
							</div>
						</div>
					</div>
				</div>
			</form>
		</div>
	</div>

	<div class="row">
		<div class="col-sm-12 form-inline">
			<div class="form-group">
				<label for="filter" class="sr-only">Filter</label>
				<input type="text" class="form-control" v-model="filter" placeholder="Filter">
			</div>
		</div>
		<div class="col-md-12">
			<div class="table-responsive">
				<datatable :columns="columns" :data="payments" :filter-by="filter" style="margin-bottom: 5px;">
					<template scope="{ row }">
						<tr>
							<td>{{ row.TPayment_invoice }}</td>
							<td>{{ row.TPayment_date }}</td>
							<td>{{ row.Brunch_name }}</td>
							<td>{{ row.transaction_type }}</td>
							<td>{{ row.payment_by }}</td>
							<td>{{ row.TPayment_amount }}</td>
							<td>{{ row.TPayment_notes }}</td>
							<td>{{ row.TPayment_Addby }}</td>
							<td>
								<?php if($this->session->userdata('accountType') != 'u'){?>
								<button type="button" class="button edit" @click="editPayment(row)">
									<i class="fa fa-pencil"></i>
								</button>
								<button type="button" class="button" @click="deletePayment(row.TPayment_id)">
									<i class="fa fa-trash"></i>
								</button>
								<?php }?>
							</td>
						</tr>
					</template>
				</datatable>
				<datatable-pager v-model="page" type="abbreviated" :per-page="per_page" style="margin-bottom: 50px;"></datatable-pager>
			</div>
		</div>
	</div>
</div>

<script src="<?php echo base_url();?>assets/js/vue/vue.min.js"></script>
<script src="<?php echo base_url();?>assets/js/vue/axios.min.js"></script>
<script src="<?php echo base_url();?>assets/js/vue/vuejs-datatable.js"></script>
<script src="<?php echo base_url();?>assets/js/vue/vue-select.min.js"></script>
<script src="<?php echo base_url();?>assets/js/moment.min.js"></script>

<script>
	Vue.component('v-select', VueSelect.VueSelect);
	new Vue({
		el: '#transferPayment',
		data(){
			return {
				payment: {
					TPayment_id: 0,
					TPayment_customerID: null,
					TPayment_TransactionType: 'CP',
					TPayment_Paymentby: 'cash',
					account_id: null,
					TPayment_date: moment().format('YYYY-MM-DD'),
					TPayment_amount: '',
					TPayment_notes: ''
				},
				payments: [],
				// transfers: [],
				// selectedTransfer: {
				// 	display_name: 'Select Transfer',
				// 	Supplier_Name: ''
				// },
				transferDue: 0,
				accounts: [],
                selectedAccount: null,
				branches: [],
				selectedBranch: {
					Brunch_name: 'Select Branch'
				},
				userType: '<?php echo $this->session->userdata("accountType");?>',
				
				columns: [
                    { label: 'Transaction Id', field: 'TPayment_invoice', align: 'center' },
                    { label: 'Date', field: 'TPayment_date', align: 'center' },
                    { label: 'Branch Name', field: 'Brunch_name', align: 'center' },
					{ label: 'Transaction Type', field: 'transaction_type', align: 'center' },
					{ label: 'Payment by', field: 'payment_by', align: 'center' },
                    { label: 'Amount', field: 'TPayment_amount', align: 'center' },
                    { label: 'Description', field: 'TPayment_notes', align: 'center' },
                    { label: 'Saved By', field: 'TPayment_Addby', align: 'center' },
                    { label: 'Action', align: 'center', filterable: false }
                ],
                page: 1,
                per_page: 10,
                filter: ''
			}
		},
		computed: {
            filteredAccounts(){
                let accounts = this.accounts.filter(account => account.status == '1');
                return accounts.map(account => {
                    account.display_text = `${account.account_name} - ${account.account_number} (${account.bank_name})`;
                    return account;
                })
            },
        },
		created(){
			this.getBranches();
			// this.getTransfers();
			this.getAccounts();
			this.getTransferPayments();
		},
		methods:{
			getTransferPayments(){
				let data = {
					dateFrom: this.payment.TPayment_date,
					dateTo: this.payment.TPayment_date
				}
				axios.post('/get_transfer_payments', data).then(res => {
					this.payments = res.data;
				})
			},
            getBranches() {
                axios.get('/get_branches').then(res => {
                    let currentBranchId = parseInt("<?php echo $this->session->userdata('BRANCHid');?>");
                    let currentBranchInd = res.data.findIndex(branch => branch.brunch_id == currentBranchId);
                    res.data.splice(currentBranchInd, 1);
                    this.branches = res.data;
                })
            },

			getSpecialBranchDue() {
				axios.post('/get_special_branches_due', {
					customerBranchId: this.selectedBranch.brunch_id
				}).then(res => {
					// console.log(res.data);
					if(res.data.length > 0){
						this.transferDue = res.data[0].dueAmount;
					} else {
						this.transferDue = 0;
					}
				})
			},
			// getBranchDue() {
			// 	if(this.selectedBranch == null || this.selectedBranch.brunch_id == undefined){
			// 		return;
			// 	}
			// 	axios.post('/get_branches_due', { BranchId: this.selectedBranch.brunch_id }).then(res => {
			// 		if(res.data.length > 1) {
			// 			this.transferDue = 0
			// 		} else {
			// 			this.transferDue = res.data[0].due;
			// 		}
			// 	})
			// 	this.getSpecialBranchDue();
			// 	console.log(this.transferDue)	
			// 	console.log(this.paidAmount)	

			// 	this.totalDue = this.transferDue - this.paidAmount;

			// },
			// getTransfers(){
			// 	axios.post('/get_transfers', { status: 'a' }).then(res => {
			// 		this.transfers = res.data;
            //         // console.log(this.transfers)
			// 	})
			// },
			// getTransferInvoiceDue(){
			// 	if(this.selectedTransfer == null || this.selectedTransfer.Supplier_SlNo == undefined){
			// 		return;
			// 	}

			// 	if(this.selectedSupplier.is_customer == true) {
			// 		axios.post('/get_customer_combine_due',{code: this.selectedSupplier.Supplier_Code})
			// 		.then(res=>{
			// 			if(res.data.length > 0){
			// 				this.supplierDue = res.data[0].dueAmount;
			// 			} else {
			// 				this.supplierDue = 0;
			// 			}
			// 		})
			// 	} else {
			// 		axios.post('/get_supplier_due', {supplierId: this.selectedSupplier.Supplier_SlNo})
			// 		.then(res => {
			// 			this.supplierDue = res.data[0].due;
			// 		})
			// 	}
			// },
			getAccounts(){
                axios.get('/get_bank_accounts')
                .then(res => {
                    this.accounts = res.data;
                })
            },
			saveTransferPayment(){
				if(this.payment.TPayment_Paymentby == 'bank'){
					if(this.selectedAccount == null){
						alert('Select an account');
						return;
					} else {
						this.payment.account_id = this.selectedAccount.account_id;
					}
				} else {
					this.payment.account_id = null;
				}
				if(this.selectedBranch == null || this.selectedBranch.brunch_id == undefined) {
					alert('Select Branch');
					return;
				}

				this.payment.TPayment_customerID = this.selectedBranch.brunch_id;

				let url = '/add_transfer_payment';
				if(this.payment.TPayment_id != 0){
					url = '/update_transfer_payment';
				}
				// console.log(this.payment)
				axios.post(url, this.payment).then(res => {
					let r = res.data;
					alert(r.message);
					if(r.success){
						this.resetForm();
						this.getTransferPayments();
					}
				})
			},
			editPayment(payment){
				let keys = Object.keys(this.payment);
				keys.forEach(key => {
					this.payment[key] = payment[key];
				})

				this.selectedBranch = {
					brunch_id: payment.TPayment_customerID,
					Brunch_name: payment.Brunch_name					
				}

				if(payment.TPayment_Paymentby == 'bank'){
					this.selectedAccount = {
						account_id: payment.account_id,
						account_name: payment.account_name,
						account_number: payment.account_number,
						bank_name: payment.bank_name,
						display_text: `${payment.account_name} - ${payment.account_number} (${payment.bank_name})`
					}
				}
			},
			deletePayment(paymentId){
				let deleteConfirm = confirm('Are you sure?');
				if(deleteConfirm == false){
					return;
				}
				axios.post('/delete_transfer_payment', {paymentId: paymentId}).then(res => {
					let r = res.data;
					alert(r.message);
					if(r.success){
						this.getTransferPayments();
					}
				})
			},
			resetForm(){
				this.payment.TPayment_id = 0;
				this.payment.TPayment_customerID = '';
				this.payment.TPayment_amount = '';
				this.payment.TPayment_notes = '';
				
				this.selectedBranch = {
					Brunch_name: 'Select Branch'
				};
				
				this.transferDue = 0;
			}
		}
	})
</script>