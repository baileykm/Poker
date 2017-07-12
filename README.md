# Poker 
###### - An Example for Server-side Push

This is a course example for *"Application Development Technology"*, powered by Bailey :)

Server-side browser push technologies have been around for a while in one way or another, ranging from from crude browser polling to Flash enabled frameworks. There are so many evolution and mechanics of server-push technologies, including:

 - Server streaming
 - Polling and long Polling
 - Comet
 - Web Sockets
 - Server-sent Events

In this example, we will use the **Web Sockets** to build a traditional poker game, known as *"Landlord Fighter"*. The rules of the game can be searched on [Baidu](https://www.baidu.com/s?wd=斗地主规则及玩法).

### Deployment and Run
1. Build the PHP runtime environment. For simplicity, you can download and install [phpStudy](http://www.phpstudy.net). *(Maybe you need to configure the `listening port`)*

2. `Unzip` or `git clone` this project into the *Web-Root* folder. eg. D:/phpStudy/WWW

3.  Run `start_game.bat` to start the WebSocket service.

4.  Now, you can play the game at [http://localhost/Poker](http://localhost/Poker). 

### Toolkits & libraries
Some toolkits/libraries were used in this project, including:

- JQuery
- Bootstrap
- AngularJS
- [angular-ui-bootstrap](http://angular-ui.github.io/bootstrap/)
- [AngularJS Toaster](https://github.com/CodeSeven/toastr)
- [jquery-confirm](http://craftpip.github.io/jquery-confirm/)
- [Workerman](http://www.workerman.net/) *- This is a PHP Socket framework*

Ok, that all, Enjoy!