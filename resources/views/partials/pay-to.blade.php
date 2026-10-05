{{-- "Please pay to": the one account chosen for invoices (Bank accounts → Show on invoices). --}}
@if ($account)
    <h3 style="margin-top:1.5rem">Please pay to</h3>
    <table>
        <thead><tr><th>Bank / wallet</th><th>Account number</th><th>Account name</th></tr></thead>
        <tbody>
            <tr><td>{{ $account->provider }}</td><td><strong>{{ $account->account_number }}</strong></td><td>{{ $account->account_name }}</td></tr>
        </tbody>
    </table>
    <p class="small muted">Please quote the invoice number with your payment and send us the transaction slip.</p>
@endif
