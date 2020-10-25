# DeployUtil

## Overview

CI/CD utility using GitHub Webhook.  
**NOTE:** This repository is expected to be located at the document root of the server with Apache2 installed.  

## Usage

First, clone this repository and place development server.  
And if you would like to execute `git pull`, you should confirm if the server can pull from target repository.  

### Define configuration

You must place `config.json` somewhere on the server before using this program.  
(this repository contains `.htaccess` to protect `config.json`, but it's better to place it above the document root.)  

The template of `config.json` is shown below:

```json:config.json
    {
        "deployment_targets": [
            {
                "repo_name": "GitHub/Example",
                "ref": "refs/heads/develop",
                "secret_hash": "f121cdb5dfebc4505a0ca8093ea27cfa3adda7dc",
                "repo_dir": "/var/www/Example",
                "execute": "/usr/local/bin/git checkout -b develop origin/develop"
            }
        ]
    }
```

the details of each key in the configuration is shown below:

 - repo_name: target Reposiroty Name (e.g. `ReactiveX/RxSwift`)
 - ref: the trigger information. (sent from Webhook. e.g. `refs/heads/master`)
 - secret_hash: **Nullable** sha-1 hash of a private key that can be defined when configuring Webhook
 - repo_dir: directory for repository placing
 - execute: command executed when Webhook triggered

### WebHook setting

under construction...  
  
At this time, we are certain to include `?from=github` in the GET parameters.  

## Configuration option

under construction...  

(In the current version you need to write the string directly to the `ref`, but in the future some triggers will be able to be set to json object-like.)  