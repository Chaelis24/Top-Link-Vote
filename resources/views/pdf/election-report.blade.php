<!DOCTYPE html>
<html>

<head>
    <title>Election Report</title>
    <style>
        body {
            font-family: sans-serif;
            color: #333;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }

        .stats {
            margin: 20px 0;
            width: 100%;
            border-collapse: collapse;
        }

        .stats td {
            padding: 10px;
            border: 1px solid #ddd;
        }

        .dept-title {
            background: #f4f4f4;
            padding: 5px;
            margin-top: 20px;
            font-weight: bold;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        th {
            background-color: #673ab7;
            color: white;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>Official Election Report</h1>
        <p>Generated on: {{ $date }}</p>
    </div>

    <h3>General Statistics</h3>
    <table class="stats">
        <tr>
            <td><strong>Total Votes:</strong> {{ number_format($totalVotes) }}</td>
            <td><strong>Total Candidates:</strong> {{ $candidatesCount }}</td>
            <td><strong>Turnout Rate:</strong> {{ $turnout }}</td>
        </tr>
    </table>

    @foreach ($tallyByDept as $dept => $candidates)
        <div class="dept-title">{{ $dept }} Department Standings</div>
        <table>
            <thead>
                <tr>
                    <th>Candidate Name</th>
                    <th>Position</th>
                    <th>Votes Received</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($candidates as $candidate)
                    <tr>
                        <td>{{ $candidate['label'] }}</td>
                        <td>{{ $candidate['position'] }}</td>
                        <td>{{ number_format($candidate['votes']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    <div style="margin-top: 50px; text-align: center; font-size: 10px; color: #777;">
        This is a system-generated report.
    </div>
</body>

</html>
