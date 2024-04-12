"use strict";
let servers = {

};

$('.server').each(function () {
    let interval = $(this).data('update');
    let id = $(this).data('id');

    if (interval === undefined) {
        interval = 10;
    }
    servers[id] = [];
    servers[id]['interval'] = interval;
    servers[id]['current'] = interval;
    servers[id]['running'] = true;
    servers[id]['el'] = $(this);

    updateServer(servers[id]['el']);
});

$('.server-allsky-btn').on('click', function () {
    event.stopPropagation();

    var action = $(this).data('action');
    var id = $(this).data('id');

    servers[id]['running'] = false;

    if (window.confirm('Are you sure you wish to ' + action + ' Allsky?')) {
        $.ajax({
            method: 'GET',
            url: '/server/allsky/action/' + action + '/' + id,
            context: this
        }).done(function (result) {
        });
    }

    servers[id]['running'] = true;
});

$('.server-action').on('click', function () {
    event.stopPropagation();

    let action = $(this).data('action');
    let id = $(this).data('id');
    let name = $(this).data('name');
    let url = '/server/action/' + action + '/' + id;

    if (action == 'allshutdown') {
        url = '/server/action/' + action
    }
    if (window.confirm('Are you sure you wish to ' + action + ' ' + name + '?')) {
        $.ajax({
            method: 'GET',
            url: url,
            context: this
        }).done(function (result) {
        });
    }
});

updateTimer();

function updateServer(el) {

    let id = $(el).data('id');

    $.ajax({
        method: 'GET',
        url: '/server/' + id,
        context: this
    }).done(function (result) {

        let progBar = servers[id]['el'].find('.progress-bar');
        let piIcon =  servers[id]['el'].find('.info-box-icon');  
        servers[id]['el'].find('.progress-text').html('');
        let statusEl = $('#server-status-' + result.id);

        if (result.running == 1) {
            statusEl.removeClass('bg-danger');
            statusEl.addClass('bg-success');
            statusEl.html('UP');

            piIcon.removeClass('bg-danger');
            piIcon.addClass('bg-success');

            $(el).fadeTo('slow', 1, function () {
            })

            $(el).find('.server-cpu').html(result.cpu + '%');
            $(el).find('.server-temp').html(result.temp + '&degC');
            $(el).find('.server-disk').html(result.disk + '/' + result.disksize);

            $(el).find('.server-allsky').html(result.allskytext);
            if (result.allsky == 1) {
                $(el).find('.server-allsky-start').addClass('disabled');
                $(el).find('.server-allsky-stop').removeClass('disabled');
                $(el).find('.server-allsky-restart').removeClass('disabled');
            } else {
                $(el).find('.server-allsky-start').removeClass('disabled');
                $(el).find('.server-allsky-stop').addClass('disabled');
                $(el).find('.server-allsky-restart').addClass('disabled');
                if (result.allskytext == 'Not Installed') {
                    $(el).find('.server-allsky-start').addClass('disabled');
                }
            }
        } else {
            statusEl.removeClass('bg-success');
            statusEl.addClass('bg-danger');
            statusEl.html('DOWN');

            piIcon.removeClass('bg-success');
            piIcon.addClass('bg-danger');

            $(el).fadeTo('slow', 0.5, function () {
            })

            $(el).find('.server-cpu').html('N/A');
            $(el).find('.server-temp').html('N/A');
            $(el).find('.server-disk').html('N/A');
            $(el).find('.server-allsky').html('N/A');

            $(el).find('.server-allsky-btn').addClass('disabled');
        }

        let tt = 56;
    });

}

function updateTimer() {
    for (const id in servers) {

        if (servers[id]['running']) {
            let progBar = servers[id]['el'].find('.progress-bar');
            let percent = (100 / servers[id]['interval']) * servers[id]['current'];

            if (servers[id]['current'] == 0) {
                servers[id]['el'].find('.progress-text').html('Updating');
                updateServer(servers[id]['el']);
            }

            if (servers[id]['current'] < 0) {
                servers[id]['current'] = servers[id]['interval'];
                percent = (100 / servers[id]['interval']) * servers[id]['current'];
            }

            progBar.width(percent + '%');

            if (percent <= 10) {
                progBar.addClass('bg-danger');
                progBar.removeClass('bg-warning');
                progBar.removeClass('bg-sucess');
            } else {
                if (percent <= 30) {
                    progBar.removeClass('bg-danger');
                    progBar.addClass('bg-warning');
                    progBar.removeClass('bg-sucess');
                } else {
                    progBar.removeClass('bg-danger');
                    progBar.removeClass('bg-warning');
                    progBar.addClass('bg-sucess');
                }
            }
            servers[id]['current'] -= 1;
        }
    }

}

setInterval(updateTimer, 1000);