<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Deposit;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Withdrawal;
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
        "AG" => "Agent",
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
        if ($report == 'WI') {
            return view('admin.reports.params.'.$report);
        }
        if ($report == 'CB') {
            return view('admin.reports.params.'.$report);
        }
        if ($report == 'TR') {
            return view('admin.reports.params.'.$report);
        }
        if ($report == 'CO') {
            return view('admin.reports.params.'.$report);
        }
    }

    public function generate(Request $request)
    {
        // dd($request->all());
        // Validate the request data
        $request->validate([
            'report_type' => 'required',
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
        $singall = $request->singall;

        $startDate = date('Y-m-d', strtotime($start));
        $endDate = date('Y-m-d', strtotime($end));

        // Check the report type and return the corresponding view
        if ($reportType == 'WI') {
            if ($reportType == 'WI') {
                return $this->generateReportWithdrawal($phone, $startDate, $endDate, $currency);
            }
        } elseif ($reportType == 'DE') {
            return $this->generateReportDeposit($phone, $startDate, $endDate, $currency);
        } elseif ($reportType == 'CU') {
            return $this->generateUserReport($phone, $startDate, $endDate, $currency);
        } elseif ($reportType == 'CB') {
            return $this->generateCustomerBalance($phone, $startDate, $endDate);
        } elseif ($reportType == 'TR') {
            return $this->generateTransaction($phone, $startDate, $endDate, $status);
        } elseif ($reportType == 'CO') {
            return $this->generateCommision($startDate, $endDate, $status);
        } else {
            // Handle unknown report type
            return redirect()->back()->with('error', 'Invalid report type.');
        }
    }



    public function getCurrencies()
    {
        $currencies = Currency::all();

        return response()->json($currencies);
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

    public function generateCustomerBalance($phone, $startDate, $endDate)
    {
        $users = User::all();
        $wallets = '';
        $isAll = false;
        if ($phone != null) {
            $user = User::where(function ($query) use ($phone) {
                $query->where('phone1', 'like', '%' . $phone . '%')
                ->orWhere('phone2', 'like', '%' . $phone . '%')
                ->orWhere('phone3', 'like', '%' . $phone . '%')
                ->orWhere('formattedPhone', 'like', '%' . $phone . '%');
            })->first();

            $userId = $user->id;

            $wallets = Wallet::with('currency')->where('user_id', $userId)->get();
            $users = $user;
            $isAll = false;
        } elseif ($phone == null) {
            $wallets = Wallet::with('currency')->get();
            $isAll = true;
        } else {
            return redirect()->back()->with('error', 'User not found for the provided phone number.');
        }

        return view('admin.reports.cb_template', compact('wallets', 'users', 'startDate', 'endDate', 'isAll'));
     }


private function getInitialBalance($userId, $currency, $startDate)
{
    // Fetch the initial balance at the beginning of the statement period
    $initialBalance = Transaction::where('user_id', $userId)
        ->when($currency, function ($query) use ($currency) {
            return $query->where('currency_id', $currency);
        })
        ->where('status', 'Success')
        ->whereDate('created_at', '<', $startDate)
        ->orderBy('created_at', 'desc')
        ->value('balance');

    return $initialBalance ?: 0;
}

    public function generateTransaction($phone, $startDate, $endDate, $status)
    {
        $user = User::all();
        $wallets = '';
        $isAll = false;
        if ($phone != null) {
            $user = User::where(function ($query) use ($phone) {
                $query->where('phone1', 'like', '%' . $phone . '%')
                ->orWhere('phone2', 'like', '%' . $phone . '%')
                ->orWhere('phone3', 'like', '%' . $phone . '%')
                ->orWhere('formattedPhone', 'like', '%' . $phone . '%');
            })->first();

            $userId = $user->id;

            $transactions = Transaction::where('user_id', $userId)
                ->with('currency', 'transaction_type', 'user')
                ->where('status', $status)
                ->whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate)
                ->get();
        } elseif ($phone == null) {
            $transactions = Transaction::with('currency')
                ->where('status', $status)
                ->whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate)
                ->get();
            $isAll = true;
        } else {
            return redirect()->back()->with('error', 'User not found for the provided phone number.');
        }

        return view('admin.reports.tr_template', compact('transactions', 'user', 'startDate', 'endDate', 'isAll'));
    }

    public function generateCommision($startDate, $endDate, $status)
    {
        // dd($startDate, $endDate, $status);
        $transactions = Transaction::with('currency', 'transaction_type')
            ->where('status', $status)
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->get();

        return view('admin.reports.co_template', compact('transactions', 'startDate', 'endDate'));
    }

    public function getStatuses()
    {
        $statuses = Transaction::distinct()->pluck('status')->all();

        return response()->json($statuses);
    }

    public function generateReportDeposit($phone, $startDate, $endDate, $currency)
    {
        // Initialize variables
        $users = '';
        $allCustomer = '';
        $userId = null;

        // Check if "All Customer" is selected
        if (empty($phone)) {
            // Handle the case for "All Customer"
            // Fetch all deposits without filtering by user phone
            $deposits = Deposit::with(['currency'])
                ->where('currency_id', $currency)
                ->where('status', 'Success')
                ->whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate)
                ->get();

            $users = User::get();
        } else {
            // "Single Customer" is selected, find the user based on the provided phone number
            $users = User::where('phone1', 'like', '%' . $phone . '%')
            ->orWhere('phone2', 'like', '%' . $phone . '%')
            ->orWhere('phone3', 'like', '%' . $phone . '%')
            ->orWhere('formattedPhone', 'like', '%' . $phone . '%')
                ->first();

            // Handle the case where the user is not found
            if (!$users) {
                return redirect()->back()->with('error', 'User not found for the provided phone number.');
            }

            $allCustomer = 'allCustomer';

            // Retrieve the user ID
            $userId = $users->id;

            // Query deposit transactions based on the user ID, date range, and currency
            $deposits = Deposit::with(['currency'])
                ->where('user_id', $userId)
                ->where('currency_id', $currency)
                ->where('status', 'Success')
                ->whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate)
                ->get();
        }

        if ($deposits->isEmpty()) {
            // Handle the case where no deposit transactions are found for the provided conditions.
            return redirect()->back()->with('error', 'No deposit transactions found for the provided conditions.');
        }

        // Calculate available balance for deposits
        $availableBalance = $this->calculateAvailableBalance($deposits);

        // Query wallet balance (inside the condition where a user is found)
        $walletBalance = ($userId) ? Wallet::where('user_id', $userId)
            ->where('currency_id', $currency)
            ->value('balance') : null;

        // Pass data to the view
        return view('admin.reports.DE_template', [
            'allCustomer' => $allCustomer,
            'user' => $users,
            'deposits' => $deposits,
            'availableBalance' => $availableBalance,
            'walletBalance' => $walletBalance,
            'sdate' => $startDate,
            'edate' => $endDate,
        ]);
    }

    public function generateReportWithdrawal($phone, $startDate, $endDate, $currency)
    {
        // Initialize variables
        $users = '';
        $allCustomer = '';
        $userId = null;

        // Check if "All Customer" is selected
        if (empty($phone)) {
            // Handle the case for "All Customer"
            // Fetch all withdrawals without filtering by user phone
            $withdrawals = Withdrawal::with(['currency'])
                ->where('currency_id', $currency)
                ->where('status', 'Success')
                ->whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate)
                ->get();

            $users = User::get();
        } else {
            // "Single Customer" is selected, find the user based on the provided phone number
            $users = User::where('phone1', 'like', '%' . $phone . '%')
            ->orWhere('phone2', 'like', '%' . $phone . '%')
            ->orWhere('phone3', 'like', '%' . $phone . '%')
            ->orWhere('formattedPhone', 'like', '%' . $phone . '%')
                ->first();

            // Handle the case where the user is not found
            if (!$users) {
                return redirect()->back()->with('error', 'User not found for the provided phone number.');
            }

            $allCustomer = 'allCustomer';

            // Retrieve the user ID
            $userId = $users->id;

            // Query withdrawal transactions based on the user ID, date range, and currency
            $withdrawals = Withdrawal::with(['currency'])
                ->where('user_id', $userId)
                ->where('currency_id', $currency)
                ->where('status', 'Success')
                ->whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate)
                ->get();
        }

        if ($withdrawals->isEmpty()) {
            // Handle the case where no withdrawal transactions are found for the provided conditions.
            return redirect()->back()->with('error', 'No withdrawal transactions found for the provided conditions.');
        }

        // Calculate available balance for withdrawals
        $availableBalance = $this->calculateAvailableBalance($withdrawals);

        // Query wallet balance (inside the condition where a user is found)
        $walletBalance = ($userId) ? Wallet::where('user_id', $userId)
            ->where('currency_id', $currency)
            ->value('balance') : null;

        // Pass data to the view
        return view('admin.reports.WD_template', [
            'allCustomer' => $allCustomer,
            'user' => $users,
            'withdrawals' => $withdrawals,
            'availableBalance' => $availableBalance,
            'walletBalance' => $walletBalance,
            'sdate' => $startDate,
            'edate' => $endDate,
        ]);
    }



    public function generateUserReport($phone, $startDate, $endDate, $currency)
{
    // Find the user based on the provided phone number
    $user = User::where(function ($query) use ($phone) {
        $query->where('phone1', 'like', '%' . $phone . '%')
        ->orWhere('phone2', 'like', '%' . $phone . '%')
        ->orWhere('phone3', 'like', '%' . $phone . '%')
        ->orWhere('formattedPhone', 'like', '%' . $phone . '%');
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
        // ->where('status', 'Success')
        ->whereIn('transaction_type_id', $allTransactionTypes)
        ->whereDate('created_at', '>=', $startDate)
        ->whereDate('created_at', '<=', $endDate)
        ->get();

    if ($transactions->isEmpty()) {
        // Handle the case where no transactions are found for the provided conditions.
        return redirect()->back()->with('error', 'No transactions found for the provided conditions.');
    }

    // Initialize opening balance
    $openingBalance = $this->initializeOpeningBalance($userId, $currency, $startDate, $endDate);

    // Calculate available balance
    $availableBalance = $this->calculateAvailableBalance($transactions, $openingBalance);

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
        'openingBalance'=>$openingBalance,
        'sdate' => $startDate,
        'edate' => $endDate
    ]);
}

// Add this function to calculate the opening balance
private function initializeOpeningBalance($userId, $currency, $startDate, $endDate)
{
    // Query transactions for the user and currency before the start date
    $openingTransactions = Transaction::where('user_id', $userId)
        ->where('currency_id', $currency)
        ->where('status', 'Success')
        ->whereDate('created_at', '<=', $startDate)
        ->get();

    // Calculate and return the opening balance
    return $this->calculateOpeningBalance($openingTransactions);
}

private function calculateOpeningBalance($transactions)
{
    // Calculate the opening balance based on the transactions
    $openingBalance = 0;

    foreach ($transactions as $transaction) {
        $openingBalance += $transaction->total;
    }

    return $openingBalance;
}


}