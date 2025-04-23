<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;

class FilesTest extends TestCase
{
    /**
     * Create a file
     */

    /**
     * @test
     */
    public function create_file_as_guest403(): void
    {
        $image = Storage::disk('local')->get('/test-data/image.png');

        $response = $this->json('POST', '/core/v1/files', [
            'is_private' => false,
            'mime_type' => 'image/png',
            'alt_text' => 'image description',
            'file' => 'data:image/png;base64,'.base64_encode($image),
        ]);

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @test
     */
    public function create_png_file_as_service_admin201(): void
    {
        $image = Storage::disk('local')->get('/test-data/image.png');

        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceAdmin($service);

        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/files', [
            'is_private' => false,
            'mime_type' => 'image/png',
            'alt_text' => 'image description',
            'file' => 'data:image/png;base64,'.base64_encode($image),
        ]);

        $response->assertStatus(Response::HTTP_CREATED);

        $service->logo_file_id = $this->getResponseContent($response, 'data.id');
        $service->save();

        $response = $this->get("/core/v1/services/$service->id/logo.png");

        $this->assertEquals($image, $response->content());

        $response = $this->get("/core/v1/files/$service->logo_file_id");

        $response->assertJson([
            'data' => [
                'id' => $service->logoFile->id,
                'is_private' => false,
                'mime_type' => 'image/png',
                'alt_text' => 'image description',
                'max_dimension' => null,
                'src' => 'data:image/png;base64,'.base64_encode($image),
                'url' => $service->logoFile->url(),
                'created_at' => $service->logoFile->created_at->format(CarbonImmutable::ISO8601),
                'updated_at' => $service->logoFile->updated_at->format(CarbonImmutable::ISO8601),
            ],
        ]);
    }

    /**
     * @test
     */
    public function create_jpg_file_as_service_admin201(): void
    {
        $image = Storage::disk('local')->get('/test-data/image.jpg');

        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceAdmin($service);

        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/files', [
            'is_private' => false,
            'mime_type' => 'image/jpeg',
            'alt_text' => 'image description',
            'file' => 'data:image/jpeg;base64,'.base64_encode($image),
        ]);

        $response->assertStatus(Response::HTTP_CREATED);

        $service->logo_file_id = $this->getResponseContent($response, 'data.id');
        $service->save();

        $response = $this->get("/core/v1/services/$service->id/logo.jpg");

        $this->assertEquals($image, $response->content());

        $response = $this->get("/core/v1/files/$service->logo_file_id");

        $response->assertJson([
            'data' => [
                'id' => $service->logoFile->id,
                'is_private' => false,
                'mime_type' => 'image/jpeg',
                'alt_text' => 'image description',
                'max_dimension' => null,
                'src' => 'data:image/jpeg;base64,'.base64_encode($image),
                'url' => $service->logoFile->url(),
                'created_at' => $service->logoFile->created_at->format(CarbonImmutable::ISO8601),
                'updated_at' => $service->logoFile->updated_at->format(CarbonImmutable::ISO8601),
            ],
        ]);
    }

    /**
     * @test
     */
    public function create_svg_file_as_service_admin201(): void
    {
        $image = Storage::disk('local')->get('/test-data/image.svg');

        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceAdmin($service);

        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/files', [
            'is_private' => false,
            'mime_type' => 'image/svg+xml',
            'alt_text' => 'image description',
            'file' => 'data:image/svg+xml;base64,'.base64_encode($image),
        ]);

        $response->assertStatus(Response::HTTP_CREATED);

        $service->logo_file_id = $this->getResponseContent($response, 'data.id');
        $service->save();

        $response = $this->get("/core/v1/services/$service->id/logo.svg");

        $this->assertEquals($image, $response->content());
        $response = $this->get("/core/v1/files/$service->logo_file_id");

        $response->assertJson([
            'data' => [
                'id' => $service->logoFile->id,
                'is_private' => false,
                'mime_type' => 'image/svg+xml',
                'alt_text' => 'image description',
                'max_dimension' => null,
                'src' => 'data:image/svg+xml;base64,'.base64_encode($image),
                'url' => $service->logoFile->url(),
                'created_at' => $service->logoFile->created_at->format(CarbonImmutable::ISO8601),
                'updated_at' => $service->logoFile->updated_at->format(CarbonImmutable::ISO8601),
            ],
        ]);
    }

    /**
     * @test
     */
    public function create_image_file_alt_text_required_as_service_admin201(): void
    {
        $image = Storage::disk('local')->get('/test-data/image.png');

        $service = Service::factory()->create();
        $user = User::factory()->create()->makeServiceAdmin($service);

        Passport::actingAs($user);

        $response = $this->json('POST', '/core/v1/files', [
            'is_private' => false,
            'mime_type' => 'image/png',
            'file' => 'data:image/png;base64,'.base64_encode($image),
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $response = $this->json('POST', '/core/v1/files', [
            'is_private' => false,
            'mime_type' => 'image/png',
            'alt_text' => '',
            'file' => 'data:image/png;base64,'.base64_encode($image),
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $response = $this->json('POST', '/core/v1/files', [
            'is_private' => false,
            'mime_type' => 'image/jpeg',
            'alt_text' => '',
            'file' => 'data:image/jpeg;base64,'.base64_encode($image),
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $response = $this->json('POST', '/core/v1/files', [
            'is_private' => false,
            'mime_type' => 'image/svg+xml',
            'alt_text' => '',
            'file' => 'data:image/svg+xml;base64,'.base64_encode($image),
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @test
     */
    public function get_png_file_as_guest200()
    {
        $image = File::factory()->pendingAssignment()->imagePng()->create();
        $service = Service::factory()->create([
            'logo_file_id' => $image->id,
        ]);

        $response = $this->get("/core/v1/files/$image->id");

        $response->assertJson([
            'data' => [
                'id' => $image->id,
                'is_private' => $image->is_private,
                'mime_type' => $image->mime_type,
                'alt_text' => $image->meta['alt_text'],
                'max_dimension' => null,
                'src' => 'data:image/png;base64,'.base64_encode($image->getContent()),
                'url' => $image->url(),
                'created_at' => $image->created_at->format(CarbonImmutable::ISO8601),
                'updated_at' => $image->updated_at->format(CarbonImmutable::ISO8601),
            ],
        ]);

        $this->assertEquals('data:image/png;base64,'.base64_encode(Storage::disk('local')->get('/test-data/image.png')), $response->json('data.src'));
    }

    /**
     * @test
     */
    public function get_jpg_file_as_guest200()
    {
        $image = File::factory()->pendingAssignment()->imageJpg()->create();
        $service = Service::factory()->create([
            'logo_file_id' => $image->id,
        ]);

        $response = $this->get("/core/v1/files/$image->id");

        $response->assertJson([
            'data' => [
                'id' => $image->id,
                'is_private' => $image->is_private,
                'mime_type' => $image->mime_type,
                'alt_text' => $image->meta['alt_text'],
                'max_dimension' => null,
                'src' => 'data:image/jpeg;base64,'.base64_encode($image->getContent()),
                'url' => $image->url(),
                'created_at' => $image->created_at->format(CarbonImmutable::ISO8601),
                'updated_at' => $image->updated_at->format(CarbonImmutable::ISO8601),
            ],
        ]);

        $this->assertEquals('data:image/jpeg;base64,'.base64_encode(Storage::disk('local')->get('/test-data/image.jpg')), $response->json('data.src'));
    }

    /**
     * @test
     */
    public function get_svg_file_as_guest200()
    {
        $image = File::factory()->pendingAssignment()->imageSvg()->create();
        $service = Service::factory()->create([
            'logo_file_id' => $image->id,
        ]);

        $response = $this->get("/core/v1/files/$image->id");

        $response->assertJson([
            'data' => [
                'id' => $image->id,
                'is_private' => $image->is_private,
                'mime_type' => $image->mime_type,
                'alt_text' => $image->meta['alt_text'],
                'max_dimension' => null,
                'src' => 'data:image/svg+xml;base64,'.base64_encode($image->getContent()),
                'url' => $image->url(),
                'created_at' => $image->created_at->format(CarbonImmutable::ISO8601),
                'updated_at' => $image->updated_at->format(CarbonImmutable::ISO8601),
            ],
        ]);

        $this->assertEquals('data:image/svg+xml;base64,'.base64_encode(Storage::disk('local')->get('/test-data/image.svg')), $response->json('data.src'));
    }

    /**
     * @test
     */
    public function display_png_file_as_guest200()
    {
        $image = File::factory()->pendingAssignment()->imagePng()->create();
        $service = Service::factory()->create([
            'logo_file_id' => $image->id,
        ]);

        $response = $this->get("/core/v1/images/$image->filename");

        $response->assertStatus(Response::HTTP_OK);

        $response->assertHeader('Content-Disposition', 'inline; filename='.$image->filename);

        $this->assertEquals(Storage::disk('local')->get('/test-data/image.png'), $response->streamedContent());
    }

    /**
     * @test
     */
    public function display_jpeg_file_as_guest200()
    {
        $image = File::factory()->pendingAssignment()->imageJpg()->create();
        $service = Service::factory()->create([
            'logo_file_id' => $image->id,
        ]);

        $response = $this->get("/core/v1/images/$image->filename");

        $response->assertStatus(Response::HTTP_OK);

        $response->assertHeader('Content-Disposition', 'inline; filename='.$image->filename);

        $this->assertEquals(Storage::disk('local')->get('/test-data/image.jpg'), $response->streamedContent());
    }

    /**
     * @test
     */
    public function display_svg_file_as_guest200()
    {
        $image = File::factory()->pendingAssignment()->imageSvg()->create();
        $service = Service::factory()->create([
            'logo_file_id' => $image->id,
        ]);

        $response = $this->get("/core/v1/images/$image->filename");

        $response->assertStatus(Response::HTTP_OK);

        $response->assertHeader('Content-Disposition', 'inline; filename='.$image->filename);

        $this->assertEquals(Storage::disk('local')->get('/test-data/image.svg'), $response->streamedContent());
    }
}
