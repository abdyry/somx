<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Deposit;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public $report_types = [
        'CU' => 'Customer Statement',
        'CB' => 'Customer Balance',
        'DE' => 'Deposits',
        'WI' => 'Withdrawals',
        'CO' => 'Commission',
        'TR' => 'Transactions',
        // "AG" => "Agent",
        'TO' => 'Top-Up',
        'US' => 'USSD',
    ];

    public function index()
    {
        $data['menu'] = 'reports';

        $data['from'] = $data['to'] = Carbon::now()->format('Y-m-d');

        $data['report_types'] = $this->report_types;

        $users = User::all();

        return view('admin.reports.index', $data, compact('users'));
    }

    public function params(Request $request)
    {
        $report = $request->report;
        if ($report == 'CU') {
            return view('admin.reports.params.'.$report);
        }
        if ($report == 'DE') {
            return view('admin.reports.params.'.$report);
        }
        if ($report == 'CB') {
            return view('admin.reports.params.'.$report);
        }
        if ($report == 'TR') {
            return view('admin.reports.params.'.$report);
        }
    }

    public function generate(Request $request)
    {
        // Validate the request data
        $request->validate([
            'report_type' => 'required',
            'phone' => 'required|exists:users,phone',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        // Get parameters from the validated request
        $reportType = $request->report_type;
        $phone = $request->phone;
        $start = $request->start_date;
        $end = $request->end_date;
        $currency = $request->currencyID;
        $status = $request->status;

        $startDate = date('Y-m-d', strtotime($start));
        $endDate = date('Y-m-d', strtotime($end));

        // Check the report type and return the corresponding view
        if ($reportType == 'WI') {
            return $this->showDeposit();
        } elseif ($reportType == 'DE') {
            return $this->generateReportDeposit($phone, $startDate, $endDate, $currency);
        } elseif ($reportType == 'CU') {
            return $this->generateUserReport($phone, $startDate, $endDate, $currency);
        } elseif ($reportType == 'CB') {
            return $this->generateCustomerBalance($phone, $startDate, $endDate);
        } 
         elseif ($reportType == 'TR') {
            return $this->generateCustomerBalance($phone, $startDate, $endDate);
        } else {
            // Handle unknown report type
            return redirect()->back()->with('error', 'Invalid report type.');
        }
    }

    public function generateUserReport($phone, $startDate, $endDate, $currency)
    {
        // Find the user based on the provided phone number
        $user = User::where(function ($query) use ($phone) {
            $query->where('phone', $phone)
                ->orWhere('formattedPhone', $phone);
        })->first();

        if (!$user) {
            // Handle the case where the user is not found
            return redirect()->back()->with('error', 'User not found for the provided phone number.');
        }

        // Retrieve the user ID
        $userId = $user->id;

        // Query all transaction types
        $transactionTypes = TransactionType::all();

        // Query transactions based on the user ID, date range, and all transaction types
        $allTransactionTypes = $transactionTypes->pluck('id')->toArray();

        $transactions = Transaction::where('user_id', $userId)
            ->where('currency_id', $currency)
            ->where('status', 'Success')
            ->whereIn('transaction_type_id', $allTransactionTypes)
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->get();

        if ($transactions->isEmpty()) {
            // Handle the case where no transactions are found for the provided conditions.
            return redirect()->back()->with('error', 'No transactions found for the provided conditions.');
        }

        // Calculate available balance
        $availableBalance = $this->calculateAvailableBalance($transactions);

        // Query wallet balance
        $walletBalance = Wallet::where('user_id', $userId)
            ->where('currency_id', $currency)
            ->value('balance');

        // Pass data to the view
        return view('admin.reports.user', [
            'user' => $user,
            'transactions' => $transactions,
            'transactionTypes' => $transactionTypes,
            'availableBalance' => $availableBalance,
            'walletBalance' => $walletBalance,
            'sdate' => $startDate,
            'edate' => $endDate,
        ]);
    }

    private function calculateAvailableBalance($transactions)
    {
        // Calculate the available balance based on the transactions
        $balance = 0;

        foreach ($transactions as $transaction) {
            // Determine if the transaction is debit or credit
            $transactionType = $transaction->total >= 0 ? 'credit' : 'debit';

            // Update the balance based on the transaction type
            if ($transactionType === 'credit') {
                $balance += $transaction->total;
            } else {
                $balance -= abs($transaction->total);
            }

            // Add the transaction type to the transaction object (optional)
            $transaction->transactionType = $transactionType;
        }

        return $balance;
    }

    // customer generate balance
    public function generateCustomerBalance($phone, $startDate, $endDate)
    {
        $user = User::where(function ($query) use ($phone) {
            $query->where('phone', $phone)
                ->orWhere('formattedPhone', $phone);
        })->first();

        $user_id = $user->id;
        $wallets = Wallet::with('currency')->where('user_id', $user_id)->get();

        return view('admin.reports.cb_template', compact('wallets', 'user', 'startDate', 'endDate'));
    }
    public function getStatuses()
    {
        $statuses = Transaction::distinct()->pluck('status')->all();
    
    
        return response()->json($statuses);
    }
    

    // generate Deposit
    public function generateReportDeposit($phone, $startDate, $endDate, $currency)
    {
        // Find the user based on the provided phone number
        $user = User::where('phone', $phone)
            ->orWhere('formattedPhone', $phone)
            ->first();
    
        if (!$user) {
            // Handle the case where the user is not found
            return redirect()->back()->with('error', 'User not found for the provided phone number.');
        }
    
        // Retrieve the user ID
        $userId = $user->id;
    
        // Query deposit transactions based on the user ID, date range, and currency
        $deposits = Deposit::with(['currency'])
            ->where('user_id', $userId)
            ->where('currency_id', $currency)
            ->where('status', 'Success')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->get();
    
        if ($deposits->isEmpty()) {
            // Handle the case where no deposit transactions are found for the provided conditions.
            return redirect()->back()->with('error', 'No deposit transactions found for the provided conditions.');
        }
    
        // Calculate available balance for deposits
        $availableBalance= $this->calculateAvailableBalance($deposits);
    
        // Query wallet balance
        $walletBalance = Wallet::where('user_id', $userId)
            ->where('currency_id', $currency)
            ->value('balance');
    
        // Pass data to the view
        return view('admin.reports.DE_template', [
            'user' => $user,
            'deposits' => $deposits,
            'availableBalance' => $availableBalance,
            'walletBalance' => $walletBalance,
            'sdate' => $startDate,
            'edate' => $endDate,
        ]);
    }
    
}