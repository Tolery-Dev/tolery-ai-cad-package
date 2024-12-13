import * as OV from 'online-3d-viewer';
import '../css/chatbot.scss';

window.addEventListener ('livewire:init', () => {

    let parentDiv = document.getElementById ('viewer');
    let viewer = new OV.EmbeddedViewer (parentDiv, {
        camera : new OV.Camera (
            new OV.Coord3D (-1.5, 2.0, 3.0),
            new OV.Coord3D (0.0, 0.0, 0.0),
            new OV.Coord3D (0.0, 1.0, 0.0),
            45.0
        ),
        backgroundColor : new OV.RGBAColor (255, 255, 255, 255),
        defaultColor : new OV.RGBColor (200, 200, 200),
        edgeSettings : new OV.EdgeSettings (false, new OV.RGBColor (0, 0, 0), 1),
    });

    Livewire.on('obj-updated', ({objPath}) => {
        viewer.LoadModelFromUrlList([objPath])
    })
});
