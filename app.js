var app = require('http').createServer(handler)
	, io = require('socket.io').listen(app)
	, fs = require('fs')

app.listen(8080);

function handler (req, res) {
	// TODO create handler module for per-session sequence data
	fs.readFile(__dirname + '/index.html',
	function (err, data) {
		if (err) {
			res.writeHead( 500 );
			return res.end('Error loading index.html');
		}
		res.writeHead(200);
		res.end(data);
	});
}
// Socket server: 
// should use per live stream "rooms" 
// https://github.com/LearnBoost/socket.io/wiki/Rooms
io.sockets
.on( 'connection', function ( socket ) {
	socket.on('set-guid', function(data){
		console.log("set-guid:" + data['guid'] );
		socket.set('guid', data['guid'] );
	})
	// a given socket: socket
	socket.on('log', function (data) {
		console.log( "server side: log", data );
		// emit the log to all sockets for that guid: 
		for( var socketId in io.sockets.sockets ){
			var currentSocket = io.sockets.sockets[socketId];
			currentSocket.get('guid', function (err, guid) {
				console.log("ON socket:", guid );
			});
		}
		// TODO check permissions for guid broadcast
		io.sockets.emit('log', { message: data['message'], guid: data['guid'] });
	});
});