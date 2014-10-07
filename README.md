Общий концепт (REST API)
=============

Примеры запросов и ресурсов:

GET task/\d+ - получить полное описание задачи с айдишником id.  
GET task/\d+/comment - получить все комментарии для задачи id.  
POST project/\d+/task - добавить новую задачу в проект с айдишником id.

Ошибки возвращаются через HTTP Response Codes. Стандартные коды:

200 - ОК  
400 - Bad Request (если даны неправельные параметры запроса)  
401 - Unauthorized (если не прошли авторизацию)  
403 - Forbidden (если нет нужных разрешений у юзера)  
405 - Invalid Method Call (возвращается автоматически если не реализован метод)  
500 - Internal Server Error (возвращается автоматически, когда есть ошибка PHP)  

GET - запрос на получение ресурса, обычно параметры передаются в $_GET, возвращает:  
200 - OK и описание ресурса как JSON в ответе.  
404 - Not Found

POST - запрос для создания нового ресурса. Возвращает:  
201 - OK (Created) и обычно JSON с айдишником созданного ресурса.

PUT - запрос на изменение существующего ресурса. Все новые данные передаются как JSON в теле запроса. Возвращает:  
200 - OK

DELETE - запрос на удаление ресурса. Используется редко, потому что обычно ресурсы только отмечаются как удаленные, чтобы не нарушать целостности базы данных.  
204 - (OK) No Content  
404 - Not Found


Роли и права
=============
Каждому юзеру назначается системная роль (с системными правами) и роль на каждом проекте (на разных проектах роль может быть своя).

Системные права:
* system.access
* administer - админские права перекрывают все остальные.
* project.management - управление проектами (создание и удаление)
* people.management - управление людьми (создание, смена роли, смена пароля)

Права по проекту:
* tester - тестеры могут менять статус чужой задачи на (reopened или closed);
* task.management - (создание, удаление и редактирование задач);
* comment.management - (редактирование и удаление чужих комментариев);

Права, которые есть по умолчанию:
* создавший задачу (автор) может делать с ней что угодно;
* создавший коммент, так же может делать с ним все, что можно;

Таким образом PUT task работает только для автора, либо для чувака с task.management. Еще PUT работает для тестера только на смену статуса задачи.

**GET role**  
Возвращает список всех ролей  
 ``` json
Response: 
{
    "class": "userRoleList",
    "items": [
        {
            "class": "role",
            "id": 1,
            "sysrole": true,
            "sysname": "admin",
            "name": "Администратор",
            "permissions": [
                "system.access",
                "administer"
            ]
        },
        ...
    ]
}
```

**POST role**  
_access: administer_  
Создаёт новую роль  
``` json
Request: {
  sysname,
  sysrole
}
Response: {
  id : 100
}
```

**PUT role/\d+**  
_access: administer_  
Изменение роли  
``` json
request: {
    "name": "someone",
    "permissions": ["system.access","people.management"]
}
```
Поля:  
permissions  
name

**DELETE role/\d+**  
_access: administer_  
Удаляет роль. Для всех пользователь, имеющих данную роль, значение роли устанавливается пустым.



Юзеры
=============

**GET user**  
Список пользователей  
``` json
{
    from: 0,
    count: 24,
    items: [] // параметры, которые необходимо вернуть. Если отсутствуют - использовать дефолтный набор
}
```

**GET user/\d+**  
Данные пользователя

**GET user/\d+/clientSettings**  
Возвращает настройки клиента пользователя

**GET user/\d+/vars**  
Возвращает доп. информацию о пользователе  
_устарело_

**GET user/\d+/avatar**  
Возвращает ссылку на аватару юзера, чтобы скачать.
``` json
{
    "url" : "http://office.divogames.ru/todo/avatars/1.jpg",
}
```
_устарело_

**POST user**  
_access: administer, people.management_  
Создание пользователя
``` json
Request:
{
  username: "",
  email: ""
}
```

**PUT user/\d+/vars**  
_access: administer, people.management, owner_  
Изменение доп. информации о  пользователе  
_устарело_

**PUT user/\d+/clientSettings**  
_access: owner_  
Изменение настроек клиента пользователя

**PUT user/\d+/password**  
_access: people.management, user (self)_  
Изменение пароля. Админам менять пароли могут только они сами или другие админы.
``` json
Request:
{
  password: md5(text)
}
```
_устарело_


**PUT user/\d+**  
_access: administer, people.management_  
Изменение данных пользователя  
people.management не может устанавливать роль "admin", нельзя менять самому себе

Вообще people.management - читерский доступ. Можно нахоботить себе любой доступ кроме админского через фейковый аккаунт, например.
Поля для менеджера:  
deleted  
username  
role  
def_role  
group  
firstname  
lastname  

**PUT user/\d+/projectrole**  
_access: administer, people.management_  
Установить проектную роль.  
Если роль пустая - запись удаляется  
``` json
Request: {
  "project":
  "role":
}
```

**POST user/\d+/avatar?filename&size**  
_access: owner_  
Загрузить аватарку для юзера.  
filename: исходное имя картинки (по нему можно формат определить)  
size: размер данных в байтах.

Body: Бинарные данные картинки с аватаркой.


Проекты
=============

**GET project**  
Возвращает список всех проектов  
``` json
Response: {
  "class" : "projectList"
  "items" : [
    {}, // _project_object 
    {} // _project_object
  ]
}
```

**GET project/\d+**  
Возвращает описание проекта  
``` json
Response (_project_object):
{
  "class" : "project",
  "id" : 1,
  "archived" : false,
  "title" : "",
  "shorttitle" : "",
  "tagcolor"   : "",
  "created"    : "" // created_unix
}
```

**POST project**  
_access: administer, project.management_  
Добавить новый проект
``` json
Request: {
  "title": ""
}
```
``` json
Response: {
  "id" : 100
}
```

**PUT project/\d+**  
access: administer, project.management  
Редактирование проекта
``` json
{
  "title" : "",
  "shorttitle" : "",
  "tagcolor" : ""
```


Задачи
=============

**GET task/\d+/oldnote**  
Возвращает бинарник комментариев для задачи.  
_deprecated_

**GET task/\d+/oldcalendar**  
Возвращает бинарник календаря для задачи.  
_deprecated_

**GET task/\d+**  
Response  (_task_object):  
``` json
{
    "project"  : "", //айдишник проекта к которому относится таск. ~projid
    "type"     : "task, milestone, folder, issue, feature, testcase",  // (ENUM)
    "title"    : "My super task.",
    "priority" : 0, // (TINYINT)
    "status"   : "open, inprogress, finished, reopened, closed, canceled", // (ENUM)
    "assignee" : 10, // юзер, которому назначена задача. ~uid
    "parentTask" : 10, // айдишник родительской задачи или 0
    "sub-tasks" : [
        { "id" : 1000 },
        { "id" : 1001 }
    ],
    "attachments" : [
          {
              "url" : 1000,
              "filename" : 1000,
              "size" : 1000,
          }
    ],
    "estimatedEffortSeconds" : 3600,
    "startDate" : 0, // Unix timestamp
    "duration"  : 0, // целое число дней
    "deadline"  : 0, // Unix timestamp (date)
    "rootComment": 0, //
    "created" : 0, //Unix Timestamp
    "createdby" : 0
}
```

**GET task/search?from&count&assignee&status&title&text&timesheet_from&timesheet_to&project&priority
Возвращает массив незавершенных (!!!) корневых (parentTask=0) тасков, незавершенных потому что завершенных очень много. Начиная с индекса from до индекса (from + count - 1) включительно.
По умолчанию from = 0, count = 100;
``` json
Response:
{
    "from" : "",
    "to" : "",
    "items" : [
       // Таски как в GET/task/\d+.
    ]
}
```

**POST task/\d+**  
access: administer, task.management  
Добавить новую задачу в указанный проект  
``` json
Request: {
  "title": ""
}
```
``` json
Response: {
  "id" : 100
}
```

**PUT task/\d+**  
_access: administer, task.management_
Изменить свойства существующей задачи.
``` json
// Поля для people.management:
project
type
title
priority
status
assignee
parentTask
deadline
startDate
duration
estimatedEffortSeconds

// Поля для assignee:
status

// Поля для: access: tester
status
```

**DELETE task/\d+**  
_access: administer, task.management_  
Ответ: 204? No Content  
Удалять можно только задачи верхнего уровня (не имеющие дочек)  

Микротаски
=============
**PUT microtask/\d+**
{
    “state” : “open|finished|canceled”
    “text” : “string”
}

Вложения
=============
Вложения это отдельные файлы, которые прицепляются к задаче.

**POST task/<id>/attachment?filename&size**  
_access: administer, task.management, assignee_  
Body: бинарные данные (тут надо подумать а чо делать если файл большой - сколько вообще в посте передать можно?)

настройка сервера  
php_value post_max_size 20M


Комментарии
=============
Комментарии пишутся к задачам - для отображения дискуссии. Причем, само описание задачи - это автоматически созданный корневой комментарий, а все последующие комменты вкладываются в него.

**GET task/\d+/comments**
``` json
Response: {
    "id"
    "text"
    "created"
    "createdby"
    "modified"
    "modifiedby"
    "childes": []
}
```

**POST task/\d+/comment**  
_access: comment.management, assignee, tester_
``` json
Request: {
  "text": "",
  "parent": 100,
}
Response: {
  "id" : 100,
}
```

**PUT comment/\d+**  
_access: administer, comment.management, owner_

Здесь "text" - это форматированный HTML текст с простейшими тэгами.
При редактировании комментария у него автоматически апдейтятся поля кем и когда изменено.

Поля:
text

Таймшит (работа над задачей)
=============

Когда юзер вбивает часы отработанные по задаче, они сохраняются в отдельной таблице базы.

**GET task/\d+/timesheet**
``` json
Response: [
    { “day” : 15275, “worker” : 22, “worktimeSeconds” : 3600 },
    { “day” : 15276, “worker” : 22, “worktimeSeconds” : 3600 },
]
```

Для рабочих дней сделано исключение и они создаются не POST’ом а простым PUT’ом - это как бы модификация свойств задачи.

**GET user/\d+/timesheet?day=15275**
``` json
Response: {
“totalWorktimeSeconds” : 7200,
“items” : [
    { “task” : 123, “worktimeSeconds” : 3600 },
    { “task” : 124, “worktimeSeconds” : 3600 }
    ]
}
```
Возвращает таймшит указанного юзера на указанный день по всем задачам над которыми он работал.

**PUT task/\d+/timesheet?day=15275**  
_access: administer, task.management, assignee_
Body: {
  “worker” : 22 (опционально)
  “worktimeSeconds” : “1000”
}
Залогиненый юзер должен быть либо assignee задачи, либо менеджером с пермишном task.management

Если у юзера нет прав task.management, то worker не должен указываться, т.к. автоматически подразумевается assignee задачи.

Запись удаляется если worktimeSeconds = нулю.


Календари
=============
Календарь есть общий для всех сотрудников (где отмечаются праздники и отклонения от дефолтного календаря (каждая суббота и воскресенье - выходной)) и есть персональные календари, где отмечаются отклонения от общего календаря для каждого юзера в отдельности.

**GET calendar?from=15275&count=100**  
Запрос на получение общего календаря за указанный интервал дат.
``` json
Response: [
  { "day" : 15275, "kind" : "workday|dayoff" }
]
```

**GET user/\d+/calendar?from=15275&count=100**  
Получить отличия в календаря юзера от дефолтного календаря.

**PUT calendar?day=15275**  
access: administer
Запрос на создание исключения из дефолтного календаря.
Body: {
  "kind" : "workday|dayoff"
}

**PUT user/\d+/calendar**  
_access: administer, people.management_  
Запрос на создание исключения из календаря юзера.

``` json
Body: {
  "day" : 15275,
  "kind" : "workday|dayoff"
}
```

**DELETE user/\d+/calendar?day=15275**  
access: administer, people.management  
Запрос на удаление исключения из календаря юзера.


Конвертер
=============

**GET converter/project**
конвертирует таблицу project в project_todo

**GET converter/task**
конвертирует таблицу task в todo_task

**GET converter/user**
конвертирует таблицу user в todo_user