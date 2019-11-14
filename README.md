Icinga Module for Incentage
===========================

For a special use-case this module provides a simple proxy API for special
parts of Icinga. It's purpose is to:

- grant unfiltered access to the current monitoring state of specific Hosts
  or Services
- grant access to issues in the Icinga [Eventtracker](https://github.com/Thomas-Gelf/icingaweb2-module-eventtracker)
  module

The main use-case for this was an integration with the IPC Incentage Process
Cockpit, serving requests from the [Incentage](https://www.incentage.com/)
Middleware Suite.

> Hint: I didn't have much details about that software, it showed User-Agent
> `Incentage Middleware Suite (IMS 8.03.00)`

As no ACLs are applied, access to this feature is granted:

* only for SSL-Requests
* with validated Certificates (please configure your web server accordingly)
* with a white-listed CN (Common Name)

Please configure such a white-list in your modules api.ini file. This is usually
`/etc/icingaweb2/modules/incentage/config.ini`:

```ini
[ssl]
allow_cn = "apiclient1.example.com, apiclient2.example.com"
```

In case you want to use this feature in a different scenario please let us know.
It should be easy to tweak this module accordingly.

Requests
--------

Please send HTTPS `GET` requests to `/icingaweb2/incentage/icinga/status`. The
URL accepts one single parameter, named `object`. `object` can be either
`<host_name>` or `<host_name>!<service_name>`.

Responses
---------

Response Format is a very simple UTF8-encoded HTML. Valid responses have a `result`
root node with a couple of property nodes, namely:

* host
* service (optional)
* state
* in_downtime
* acknowledged
* output

Valid `state` values for Hosts are `up`, `down`, `unreachable` and `pending`. For Services please expect `ok`, `warning`, `critical`, `unknown` and `pending`. `in_downtime` and `acknowledged` can be either `yes` or `no`.

In case an error occurs, please check the HTTP status code. The body carries a single `error` tag containing a related error message.

### Successful Response - Example

#### Header

```
HTTP/1.1 200 OK
Date: Thu, 13 Jun 2019 08:48:58 GMT
Server: Apache
Content-Length: 171
Content-Type: text/html; charset=UTF-8
```

#### Body

```html
<result>
<host>host1.example.com</host>
<state>down</state>
<in_downtime>yes</in_downtime>
<acknowledged>no</acknowledged>
<output>CRITICAL - Plugin timed out</output></result>
```

### Error Response - Example

#### Header

```
HTTP/1.1 403 Forbidden
Date: Thu, 13 Jun 2019 09:13:05 GMT
Server: Apache
Content-Length: 78
Content-Type: text/html; charset=UTF-8
```

#### Body

```html
<error>SSL CN 'attacker.example.com' is not allowed to access this resource</error>
```

Available Requests
------------------

The `<base url>` is usually `/icingaweb2/incentage`.

| Method | Relative Url        | Params | Description                               |
|--------|---------------------|--------|-------------------------------------------|
| GET    | icinga/status       | object | Get the status of an Icinga object        |
| POST   | icinga/status       | (body) | Set the Icinga status for an object       |
| GET    | eventtracker/issues | object | Get current eventtracker issues           |
| POST   | eventtracker/issue  | (body) | Create (or refresh) an eventtracker issue |
| POST   | director/import     | (body) | Trigger a defined Import Source with the given data |


Full examples
-------------

### Host Example

```
> GET https://icinga.example.com/icingaweb2/incentage/icinga/status?object=host1.example.com

< HTTP/1.1 200 OK
< Date: Thu, 13 Jun 2019 08:48:58 GMT
< Server: Apache
< Content-Length: 171
< Content-Type: text/html; charset=UTF-8
<
< <result>
< <host>host1.example.com</host>
< <state>down</state>
< <in_downtime>yes</in_downtime>
< <acknowledged>no</acknowledged>
< <output>CRITICAL - Plugin timed out</output></result>
```

### Example with an unknown Host name

```
> GET https://icinga.example.com/icingaweb2/incentage/icinga/status?object=invalid.example.com

HTTP/1.1 404 Not Found
Date: Thu, 13 Jun 2019 08:49:52 GMT
Server: Apache
Content-Length: 43
Content-Type: text/html; charset=UTF-8

<error>No such object: invalid.example.com</error>
```

### Service Example

```
> GET https://icinga.example.com/icingaweb2/incentage/icinga/status?object=host1.example.com!File%20Systems
```

Response Body:

```html
<result>
<host>host1.example.com</host>
<service>File Systems</service>
<state>critical</state>
<in_downtime>no</in_downtime>
<acknowledged>no</acknowledged>
<output>FS CRITICAL - free space: /var/log</output></result>
```

### Eventtracker Issues

In addition to the rules regarding the `object` parameter explained above,
this endpoint also accepts `object=host!*`. If no service is given, only
Host problems are shown. If a wildcard (`*`) service is given, all issues
related to that Host are shown.

```
GET https://icinga.example.com/icingaweb2/incentage/eventtracker/issues?object=some.example.com\!\*
```

```html
<result>
<issues>
<issue>
<uuid>73acf0f5-bb6a-42ea-583a-f847d14adc1f</uuid>
<status>open</status>
<severity>critical</severity>
<host_name>some.example.com</host_name>
<object_name>AD Domain Availability Health Degraded</object_name>
<object_class>Microsoft.Windows.Server.AD.ServiceComponent</object_class>
<message>More than 60% of the DCs contained in this AD Domain report an Availability Health problem</message>
<ticket_ref></ticket_ref></issue>
<issue>
<uuid>7905366e-8ba4-e3a2-9866-ecf5464d1b3a</uuid>
<status>open</status>
<severity>critical</severity>
<host_name>some.example.com</host_name>
<object_name>AD Domain Availability Health Degraded</object_name>
<object_class>Microsoft.Windows.Server.AD.Library.ServiceComponent</object_class>
<message>More than 60% of the DCs contained in this AD Domain report an Availability Health problem</message>
<ticket_ref></ticket_ref></issue></issues>
<isIcingaObject>false</isIcingaObject>
</result>
```

The result also contains an `isIcingaObject` tag. It tells whether a related
Icinga object exists.

### Director Import

This module allows to actively push data for a configured Import Source. To get
this running, you need to:

* Create a new Import Source, type "Incentage On-Demand Import"
* In your `modules/incentage/config.ini`, allow access to this source:

```ini
[director]
importsource = "Newly created Import Source"
```

* Send an XML body similar to the following one:

```xml
<Icinga>
  <Service>
    <Name>Test Service</Name>
    <Path>/Some/Where/There</Path>
  </Service>
  <Service>
    <Name>Anothoer Service</Name>
    <Path>/Some/Where/Else</Path>
  </Service>
</Icinga>
```

As Service objects have `host!service`-like keys, please do not forget to define
a related Property Modifier, write the combined key to a dedicated target column
and use that column as your Key Column.

When defining your related Sync Rule, please do not forget to import a Service
Template with `max_check_attempts = 1`.

Once you're ready, please send your HTTP POST to `incentage/director/import`.

Generic Errors
--------------

### SSL Certificate not in white-list

```
HTTP/1.1 403 Forbidden
Date: Thu, 13 Jun 2019 09:13:05 GMT
Server: Apache
Content-Length: 78
Content-Type: text/html; charset=UTF-8

<error>SSL CN 'attacker.example.com' is not allowed to access this resource</error>
```

### Something bad happens

We try hard to catch errors on server side. Please expect an error code 500 in case something goes badly wrong:

```
HTTP/1.1 500 Internal Server Error
Date: Thu, 13 Jun 2019 09:18:02 GMT
Server: Apache
Content-Length: 144
Connection: close
Content-Type: text/html; charset=UTF-8

<error>SQLSTATE[42S02]: Base table or view not found: 1146 Table 'icinga2.nowhere' doesn't exist, query was: SELECT invalid FROM nowhere</error>
```