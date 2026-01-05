<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forex Signals Digest</title>
</head>
<body>
    <h2>Forex Signals Digest ({{ $date }})</h2>

    @if (empty($rows))
        <p>No signals available.</p>
    @else
        <table cellpadding="6" cellspacing="0" border="1" style="border-collapse: collapse;">
            <thead>
                <tr>
                    <th align="left">Symbol</th>
                    <th align="left">Timeframe</th>
                    <th align="left">As of</th>
                    <th align="left">Signal</th>
                    <th align="left">Confidence</th>
                    <th align="left">Reason</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row['symbol'] }}</td>
                        <td>{{ $row['timeframe'] }}</td>
                        <td>{{ $row['as_of_date'] ?? '' }}</td>
                        <td>{{ $row['signal'] ?? '' }}</td>
                        <td>{{ $row['confidence'] ?? '' }}</td>
                        <td>{{ $row['reason'] ?? '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
