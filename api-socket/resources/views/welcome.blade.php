<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
</head>
<body>
    @vite(['resources/js/app.js'])
    {{Auth::user()->id}}
</body>
<script>
    /*setTimeout(() => {
        window.Echo.channel('channel')
            .listen('SocketEvent', (e) => {
                console.log(e);
            });
    }, 200);*/
    const userId = {{ Auth::user()->id }};

    setTimeout(() => {
        window.Echo.private('channel-private.' + userId)
            .listen('EventPrivate', (e) => {
                console.log(e);
            });
    }, 1000);
    //window.Echo.connector.pusher.connection.socket_id
</script>
</html>

