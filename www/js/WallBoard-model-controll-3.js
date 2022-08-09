var myApp = angular.module('myApp', ['ui.bootstrap', 'ngWebsocket', 'ngStorage']);

myApp.controller('myCtrl', ['$scope', '$localStorage', '$filter', '$interval', '$http', function ($scope, $localStorage, $filter, $interval, $http) {
	console.log("controller running");

	$scope.ORPs = [
		{
			'name': 'připojuji se k serveru',
			'inqueue': -2,
			'logged': -2,
			'available': -2
		}
	];

	$scope.status =
	{
		'text': 'init',
		'state': 0,
	};

	$scope.RCS =
	{
		'text': 'init',
		'state': 0,
	};

	$scope.kraje = [
		{
			'name': 'připojuji se k serveru',
			'inqueue': -2,
			'talking': -2,
			'logged': -2,
			'deadtimer': -2,
			'lastupdate': "00:00:00",
			'url': "-2"
		}
	];
}]);


myApp.filter('myNameFilter', function () {
	return function (items) {
		var result = [];
		angular.forEach(items, function (item) {
			switch (item.name) {
				case "HZSPK": item.name = "HZS PK";
					break;
				case "Kralovice": item.name = "Kralovice";
					break;
				case "Nyrany": item.name = "Nýřany";
					break;
				case "Plzen": item.name = "Plzeň";
					break;
				case "Prestice": item.name = "Přeštice";
					break;
				case "Domazlice": item.name = "Domažlice";
					break;
				case "HorsovskyTyn": item.name = "Horšovský Týn";
					break;
				case "Stribro": item.name = "Stříbro";
					break;
				case "Horazdovice": item.name = "Horažďovice";
					break;
				case "Susice": item.name = "Sušice";
					break;
				case "Paleni": item.name = "Pálení";
					break;
			}

			result.push(item);
		});
		return result;
	};
});

myApp.run(function ($websocket, $localStorage) {
	var ws = $websocket.$new({
		url: "ws://" + serverIP + "/",
		reconnect: true // it will reconnect after 2 seconds
	});
	ws.$on('$open', function () {
		console.log('onOpen!');
		var data =
			{ 'text': 'errmessage' };
		var data2 = {
			pracoviste: [{
				name: 'server OK, čekám na data',
				inqueue: -1,
				logged: -1,
				available: -1
			}
			]
		};
	})

		.$on('hasici', function (data2) {

			var scope = angular.element($("#ORPs")).scope();
			scope.$apply(function () {
				//scope.ORPs = [ {'name': 'ORP_ALL','counter' : -2},{'name':'Test','counter':-3}];

				scope.ORPs = data2.pracoviste;

				scope.ALLES = data2.alles;
				scope.PLZEN = data2.plzen;
				scope.ROKYCANY = data2.rokycany;
				scope.DOMAZLICE = data2.domazlice;
				scope.TACHOV = data2.tachov;
				scope.KLATOVY = data2.klatovy;

				//scope.ROKYCANY = [ {'name': 'ROKYCANY TEST','inqueue' : 1,'logged':3,'available':0}];

			});

			var scope = angular.element($("#kraje")).scope();
			scope.$apply(function () {
				scope.kraje = 0;

			});

			var scope = angular.element($("#status")).scope();
			scope.$apply(function () {
				scope.status.text = "Spojeno s: " + serverIP;
				scope.status.state = 0;

			});

		})
		.$on('kraje', function (data2) {
			console.log('kraje posilaji:');
			console.log(data2);

			var scope = angular.element($("#kraje")).scope();
			scope.$apply(function () {
				scope.kraje = data2.pracoviste;
			});

			var scope = angular.element($("#status")).scope();
			scope.$apply(function () {
				scope.status.text = "Spojeno s: " + serverIP;
				scope.status.state = 0;

			});

			var scope = angular.element($("#ORPs")).scope();
			scope.$apply(function () {
				scope.ORPs = 0;

			});

		})
		.$on('RCS', function (data) {
			var scope = angular.element($("#RCS")).scope();
			console.log(data.rcsstatus);
			scope.$apply(function () {
				scope.RCS.text = data.rcsstatus;
				scope.RCS.state = 0;

			});
		})

		.$on('pong', function (data2) {
			console.log('The websocket server has recieved the following data on pong:');
			console.log(data2);

			//ws.$close();
		})

		.$on('javaexception', function (data) {
			console.log('Exception incoming');
			console.log(data);

			var scope = angular.element($("#ORPs")).scope();
			scope.$apply(function () {
				scope.ORPs = 0;

			});

			var scope = angular.element($("#kraje")).scope();
			scope.$apply(function () {
				scope.kraje = 0;

			});

			var scope = angular.element($("#status")).scope();
			scope.$apply(function () {
				scope.status.text = data.text;
				scope.status.state = "exception";

			});

		})
		.$on('$close', function () {
			console.log('Closing connection to server.');
			var scope = angular.element($("#ORPs")).scope();
			scope.$apply(function () {
				scope.ORPs = 0;

			});

			var scope = angular.element($("#kraje")).scope();
			scope.$apply(function () {
				scope.kraje = 0;

			});
			var scope = angular.element($("#status")).scope();
			scope.$apply(function () {
				scope.status.text = "Nemohu navázat spojení s: " + serverIP;
				scope.status.state = "problem";

			});
		});
});
