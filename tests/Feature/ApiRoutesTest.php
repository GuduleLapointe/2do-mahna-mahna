<?php

test("status OK", function () {
    $response = $this->get("/status");

    if (
        !$response->assertStatus(200)->assertJson([
            "status" => "OK",
        ])
    ) {
        $this->markTestSkipped("API not responding");
    }
});

describe("helpers", function () {
    test("status OK", function () {
        $response = $this->get("/default_helpers/status");
        $response->assertStatus(200)->assertJson([
            "status" => "OK",
        ]);
    });

    test("/events.lsl2 should return data", function () {
        $response = $this->get("/default_helpers/events.lsl2");

        $response->assertStatus(200);
    })->depends("status OK");

    test("/events.lsl3 should return data", function () {
        $response = $this->get("/default_helpers/events.lsl3");

        $response->assertStatus(200);
    })->depends("status OK");

    test("/events.php should return data", function () {
        $response = $this->get("/default_helpers/events.php");

        $response->assertStatus(200);
    })->depends("status OK");

    test("/events.lsl should return data", function () {
        $response = $this->get("/default_helpers/events.lsl");

        $response->assertStatus(200);
    })->depends("status OK");
});

describe("/api/v2", function () {
    test("status OK", function () {
        $response = $this->get("/api/v2/status");

        $response->assertStatus(200)->assertJson([
            "status" => "OK",
        ]);
    });

    test("/events should return data", function () {
        $response = $this->get("/api/v2/events");

        $response->assertStatus(200);
    });
});

describe("/api/v3", function () {
    test("status OK", function () {
        $response = $this->get("/api/v3/status");

        $response->assertStatus(200)->assertJson([
            "status" => "OK",
            "group" => "v3",
        ]);
    });

    test("/events should return data", function () {
        $response = $this->get("/api/v3/events");

        $response->assertStatus(200);
    })->depends("status OK");

    test("/events/lsl should return data", function () {
        $response = $this->get("/api/v3/events/lsl");

        $response->assertStatus(200);
    })->depends("status OK");

    test("/events/json should return data", function () {
        $response = $this->get("/api/v3/events/json");

        $response->assertStatus(200);
    })->depends("status OK");

    test("/events/board.png should return image", function () {
        $response = $this->get("/api/v3/events/board.png");

        $response->assertStatus(200);
    })->depends("status OK");
});

describe("/api/v3/scrup", function () {
    test("status OK", function () {
        $response = $this->get("/api/v3/scrup/status");

        $response->assertStatus(200)->assertJson([
            "status" => "OK",
        ]);
    });

    test("/get-version should return data", function () {
        $response = $this->get("/api/v3/scrup/get-version");

        $response->assertStatus(200);
    })->depends("status OK");

    test("/register/server should return data", function () {
        $response = $this->post("/api/v3/scrup/register/server");

        $response->assertStatus(200);
    })->depends("status OK");

    test("/register/script should return data", function () {
        $response = $this->post("/api/v3/scrup/register/script");

        $response->assertStatus(200);
    })->depends("status OK");

    test("/register/client should return data", function () {
        $response = $this->post("/api/v3/scrup/register/client");

        $response->assertStatus(200);
    })->depends("status OK");
});

describe("/api/user", function () {
    test("should require authentication", function () {
        $response = $this->get("/api/user");

        $response->assertStatus(302); // Redirige vers la page de login
    });

    test("should return authenticated user", function () {
        $user = \App\Models\User::factory()->create();

        $response = $this->actingAs($user, "sanctum")->get("/api/user");

        $response->assertStatus(200)->assertJson([
            "id" => $user->id,
            "email" => $user->email,
        ]);
    });
});
